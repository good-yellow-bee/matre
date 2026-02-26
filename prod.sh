#!/bin/bash
# Production management script for MATRE
# Usage: ./prod.sh [start|stop|restart|status|logs|update|shell]

set -e

COMPOSE_FILES="-f docker-compose.yml -f docker-compose.prod.yml"
PROJECT_NAME="matre"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

case "${1:-help}" in
    start)
        log_info "Starting production environment..."
        docker compose $COMPOSE_FILES up -d
        log_info "Running migrations..."
        docker exec ${PROJECT_NAME}_php php bin/console doctrine:migrations:migrate --no-interaction
        log_info "Warming cache..."
        docker exec -u www-data ${PROJECT_NAME}_php php bin/console cache:warmup --env=prod
        log_info "Production started successfully!"
        docker compose $COMPOSE_FILES ps
        ;;

    stop)
        log_info "Stopping production environment..."
        docker compose $COMPOSE_FILES down
        log_info "Production stopped."
        ;;

    restart)
        log_info "Restarting production environment..."
        docker compose $COMPOSE_FILES down
        docker compose $COMPOSE_FILES up -d

        # Wait for Selenium Grid to be ready (hub + chrome-node registration)
        log_info "Waiting for Selenium Grid..."
        for i in {1..30}; do
            if curl -s http://localhost:4444/status 2>/dev/null | grep -q '"ready":true'; then
                log_info "Selenium Grid ready."
                break
            fi
            if [ $i -eq 30 ]; then
                log_warn "Selenium Grid not ready, recreating..."
                docker compose $COMPOSE_FILES up -d --force-recreate selenium-hub chrome-node
                sleep 10
            fi
            sleep 2
        done

        log_info "Production restarted."
        docker compose $COMPOSE_FILES ps
        ;;

    status)
        log_info "Production status:"
        docker compose $COMPOSE_FILES ps
        echo ""
        log_info "Workers:"
        docker compose $COMPOSE_FILES ps | grep -E "worker|scheduler" || echo "No workers running"
        ;;

    logs)
        SERVICE="${2:-}"
        if [ -z "$SERVICE" ]; then
            docker compose $COMPOSE_FILES logs -f --tail=100
        else
            docker compose $COMPOSE_FILES logs -f --tail=100 "$SERVICE"
        fi
        ;;

    update)
        log_info "Updating production..."
        log_info "Pulling latest images..."
        docker compose $COMPOSE_FILES pull
        log_info "Recreating containers..."
        docker compose $COMPOSE_FILES up -d --force-recreate
        log_info "Running migrations..."
        docker exec ${PROJECT_NAME}_php php bin/console doctrine:migrations:migrate --no-interaction
        log_info "Clearing cache..."
        docker exec -u www-data ${PROJECT_NAME}_php php bin/console cache:clear --env=prod
        log_info "Update complete!"
        docker compose $COMPOSE_FILES ps
        ;;

    shell)
        SERVICE="${2:-php}"
        log_info "Opening shell in $SERVICE..."
        docker exec -it ${PROJECT_NAME}_${SERVICE} sh
        ;;

    recreate)
        SERVICE="${2:-}"
        if [ -z "$SERVICE" ]; then
            log_error "Usage: ./prod.sh recreate <service>"
            exit 1
        fi
        log_info "Recreating $SERVICE..."
        docker compose $COMPOSE_FILES up -d --force-recreate "$SERVICE"
        log_info "$SERVICE recreated."
        ;;

    build)
        log_info "Building production images..."
        docker compose $COMPOSE_FILES build --no-cache
        log_info "Build complete."
        ;;

    worker-logs)
        log_info "Test worker logs:"
        docker compose $COMPOSE_FILES logs -f --tail=100 test-worker
        ;;

    scheduler-logs)
        log_info "Scheduler logs:"
        docker compose $COMPOSE_FILES logs -f --tail=100 scheduler
        ;;

    frontend)
        # Cleanup on unexpected exit (with error logging)
        cleanup_frontend() {
            local exit_code=$?
            if [ -d "public/build.new" ]; then
                if ! rm -rf public/build.new 2>/dev/null; then
                    log_error "Cleanup failed: Could not remove public/build.new"
                fi
            fi
            return $exit_code
        }
        trap 'cleanup_frontend' EXIT

        log_info "Building frontend assets..."

        # Parse flags
        NO_CACHE=""
        if [ "${2:-}" = "--no-cache" ]; then
            NO_CACHE="--no-cache"
            log_info "Building with --no-cache (fresh build)"
        fi

        # Docker build with warning capture
        log_info "Building Docker image..."
        BUILD_LOG=$(mktemp)
        if ! DOCKER_BUILDKIT=1 docker build $NO_CACHE --target frontend_build -t matre-frontend-build . 2>&1 | tee "$BUILD_LOG"; then
            log_error "Docker build failed"
            rm -f "$BUILD_LOG"
            exit 1
        fi

        # Check for warnings in build output
        if grep -qi "deprecated\|vulnerability\|security" "$BUILD_LOG" 2>/dev/null; then
            log_warn "Build succeeded but has warnings - review output above"
        fi
        rm -f "$BUILD_LOG"

        # Verify image has expected structure
        if ! docker run --rm matre-frontend-build ls /app/public/build >/dev/null 2>&1; then
            log_error "Built image invalid - /app/public/build not found"
            exit 1
        fi

        # Prepare temp directory
        rm -rf public/build.new
        mkdir -p public/build.new

        # Asset extraction with validation
        if ! docker run --rm -v "$(pwd)/public/build.new:/host" matre-frontend-build \
            sh -c 'cp -r /app/public/build/. /host/'; then
            log_error "Failed to extract assets from Docker image"
            rm -rf public/build.new
            exit 1
        fi

        # Validate critical files exist (semantic check instead of magic count)
        MISSING_FILES=""
        [ ! -f "public/build.new/.vite/manifest.json" ] && MISSING_FILES="${MISSING_FILES}.vite/manifest.json "
        [ -z "$(find public/build.new -name '*.js' -type f 2>/dev/null)" ] && MISSING_FILES="${MISSING_FILES}*.js "
        [ -z "$(find public/build.new -name '*.css' -type f 2>/dev/null)" ] && MISSING_FILES="${MISSING_FILES}*.css "

        if [ -n "$MISSING_FILES" ]; then
            log_error "Build validation failed. Missing: $MISSING_FILES"
            log_error "Files found:"
            find public/build.new -type f | head -20
            rm -rf public/build.new
            exit 1
        fi

        ASSET_COUNT=$(find public/build.new -type f | wc -l)
        log_info "Extracted $ASSET_COUNT asset files"

        # Tailwind validation - check file exists, size, and rule count
        TAILWIND_FILE=$(find public/build.new -name "tailwind-*.css" -type f | head -1)
        if [ -z "$TAILWIND_FILE" ]; then
            log_error "Build failed - No tailwind-*.css file found"
            rm -rf public/build.new
            exit 1
        fi

        TAILWIND_SIZE=$(wc -c < "$TAILWIND_FILE")
        # Use grep -o to count actual braces (minified CSS is one line)
        RULE_COUNT=$(grep -o '{' "$TAILWIND_FILE" 2>/dev/null | wc -l)

        if [ "$TAILWIND_SIZE" -lt 10000 ]; then
            log_error "Build failed - Tailwind CSS too small (${TAILWIND_SIZE} bytes)"
            rm -rf public/build.new
            exit 1
        fi

        if [ "$RULE_COUNT" -lt 100 ]; then
            log_error "Build failed - Tailwind CSS has only $RULE_COUNT rules (expected >100)"
            log_error "JIT may not have scanned templates. First 10 lines:"
            head -10 "$TAILWIND_FILE"
            rm -rf public/build.new
            exit 1
        fi
        log_info "Tailwind CSS: ${TAILWIND_SIZE} bytes, $RULE_COUNT rules (OK)"

        # Atomic swap with recovery
        rm -rf public/build.old 2>/dev/null || true

        if [ -d "public/build" ]; then
            if ! mv public/build public/build.old; then
                log_error "Failed to backup current build - aborting"
                exit 1
            fi
        fi

        if ! mv public/build.new public/build; then
            log_error "Failed to deploy - restoring previous build"
            [ -d "public/build.old" ] && mv public/build.old public/build
            exit 1
        fi

        # Verify atomic swap completed
        if [ -d "public/build.new" ]; then
            log_error "Atomic swap incomplete - public/build.new still exists"
            exit 1
        fi

        # Clear trap on success
        trap - EXIT

        # Permission fix - check directory exists first, detect current user
        if [ -d "public/build" ]; then
            OWNER=$(stat -c '%U' public/build 2>/dev/null || stat -f '%Su' public/build 2>/dev/null || echo "unknown")
            if [ "$OWNER" = "root" ]; then
                CURRENT_USER=$(id -un):$(id -gn)
                if command -v sudo >/dev/null 2>&1; then
                    sudo chown -R "$CURRENT_USER" public/build || log_warn "Could not fix permissions"
                else
                    log_warn "Files owned by root - may need: sudo chown -R $CURRENT_USER public/build"
                fi
            fi
        fi

        # Cache clear - verify container exists first, no silent fallback
        if ! docker ps --format '{{.Names}}' | grep -q "^${PROJECT_NAME}_php$"; then
            log_warn "Container ${PROJECT_NAME}_php not running - cache clear skipped"
            log_warn "Run manually: docker exec -u www-data <php-container> php bin/console cache:clear --env=prod"
        elif ! docker exec -u www-data ${PROJECT_NAME}_php php bin/console cache:clear --env=prod 2>&1; then
            log_error "Cache clear failed in ${PROJECT_NAME}_php"
            log_warn "Check container: docker logs ${PROJECT_NAME}_php"
        else
            log_info "Cache cleared"
        fi

        log_info "Frontend deployed successfully!"
        log_info "Old build saved to public/build.old (use 'frontend-rollback' to restore)"
        ;;

    frontend-rollback)
        if [ ! -d "public/build.old" ]; then
            log_error "No backup found in public/build.old"
            exit 1
        fi

        # Verify backup integrity before restoring
        if [ ! -f "public/build.old/.vite/manifest.json" ]; then
            log_error "Backup appears corrupted - missing .vite/manifest.json"
            log_error "Cannot safely rollback"
            exit 1
        fi

        BACKUP_FILE_COUNT=$(find public/build.old -type f | wc -l)
        log_info "Rolling back frontend ($BACKUP_FILE_COUNT files)..."

        # Handle missing public/build gracefully
        rm -rf public/build.failed 2>/dev/null || true

        if [ -d "public/build" ] || [ -L "public/build" ]; then
            if ! mv public/build public/build.failed; then
                log_error "Failed to move current build"
                exit 1
            fi
        else
            log_warn "No current public/build - restoring from backup"
        fi

        if ! mv public/build.old public/build; then
            log_error "Failed to restore backup"
            [ -d "public/build.failed" ] && mv public/build.failed public/build
            exit 1
        fi

        # Cache clear - verify container exists first, no silent fallback
        if ! docker ps --format '{{.Names}}' | grep -q "^${PROJECT_NAME}_php$"; then
            log_warn "Container ${PROJECT_NAME}_php not running - cache clear skipped"
            log_warn "Run manually: docker exec -u www-data <php-container> php bin/console cache:clear --env=prod"
        elif ! docker exec -u www-data ${PROJECT_NAME}_php php bin/console cache:clear --env=prod 2>&1; then
            log_error "Cache clear failed in ${PROJECT_NAME}_php"
            log_warn "Check container: docker logs ${PROJECT_NAME}_php"
        else
            log_info "Cache cleared"
        fi

        log_info "Rolled back successfully!"
        [ -d "public/build.failed" ] && log_info "Failed build saved to public/build.failed"
        ;;

    help|*)
        echo "MATRE Production Management"
        echo ""
        echo "Usage: ./prod.sh [command]"
        echo ""
        echo "Commands:"
        echo "  start          Start containers (reuses existing - fast, no config changes)"
        echo "  stop           Stop production"
        echo "  restart        Restart (down + up, applies config changes)"
        echo "  update         Full update after git pull (recreate + migrate + cache)"
        echo "  recreate <svc> Recreate single service (applies config/label changes)"
        echo "  status         Show container status"
        echo "  logs [svc]     Follow logs (all or specific service)"
        echo "  worker-logs    Follow test worker logs"
        echo "  scheduler-logs Follow scheduler logs"
        echo "  build          Build images (no cache)"
        echo "  shell [svc]    Open shell (default: php)"
        echo "  frontend [--no-cache]  Build and deploy frontend assets"
        echo "  frontend-rollback      Restore previous frontend build"
        echo "  help           Show this help"
        echo ""
        echo "After git pull with docker-compose changes: use 'update' or 'recreate <svc>'"
        ;;
esac
