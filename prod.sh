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
        docker exec ${PROJECT_NAME}_php php bin/console cache:warmup --env=prod
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
        docker exec ${PROJECT_NAME}_php php bin/console cache:clear --env=prod
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

    help|*)
        echo "MATRE Production Management"
        echo ""
        echo "Usage: ./prod.sh [command]"
        echo ""
        echo "Commands:"
        echo "  start          Start production (with migrations + cache warmup)"
        echo "  stop           Stop production"
        echo "  restart        Restart production"
        echo "  status         Show container status"
        echo "  logs [svc]     Follow logs (all or specific service)"
        echo "  worker-logs    Follow test worker logs"
        echo "  scheduler-logs Follow scheduler logs"
        echo "  update         Pull, recreate, migrate, clear cache"
        echo "  build          Build images (no cache)"
        echo "  shell [svc]    Open shell (default: php)"
        echo "  recreate <svc> Recreate single service"
        echo "  help           Show this help"
        ;;
esac
