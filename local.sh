#!/bin/bash
# Local development management script for MATRE
# Usage: ./local.sh [start|stop|restart|status|logs|shell]

set -e

COMPOSE_FILES="-f docker-compose.yml"
PROJECT_NAME="matre"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

case "${1:-help}" in
    start)
        log_info "Starting local development environment..."
        docker compose $COMPOSE_FILES up -d
        log_info "Running migrations..."
        docker compose $COMPOSE_FILES exec php php bin/console doctrine:migrations:migrate --no-interaction
        log_info "Local environment started!"
        docker compose $COMPOSE_FILES ps
        echo ""
        log_info "App: https://matre.local"
        log_info "Mailpit: http://localhost:8025"
        ;;

    stop)
        log_info "Stopping local environment..."
        docker compose $COMPOSE_FILES down
        log_info "Local environment stopped."
        ;;

    restart)
        log_info "Restarting local environment..."
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

        log_info "Local environment restarted."
        docker compose $COMPOSE_FILES ps
        ;;

    status)
        log_info "Local environment status:"
        docker compose $COMPOSE_FILES ps
        ;;

    logs)
        SERVICE="${2:-}"
        if [ -z "$SERVICE" ]; then
            docker compose $COMPOSE_FILES logs -f --tail=100
        else
            docker compose $COMPOSE_FILES logs -f --tail=100 "$SERVICE"
        fi
        ;;

    shell)
        SERVICE="${2:-php}"
        log_info "Opening shell in $SERVICE..."
        docker compose $COMPOSE_FILES exec "$SERVICE" sh
        ;;

    console)
        shift
        log_info "Running console command..."
        docker compose $COMPOSE_FILES exec php php bin/console "$@"
        ;;

    migrate)
        log_info "Running migrations..."
        docker compose $COMPOSE_FILES exec php php bin/console doctrine:migrations:migrate --no-interaction
        ;;

    cache)
        log_info "Clearing cache..."
        docker compose $COMPOSE_FILES exec php php bin/console cache:clear
        log_info "Cache cleared."
        ;;

    test)
        log_info "Running tests..."
        docker compose $COMPOSE_FILES exec php bin/phpunit "$@"
        ;;

    phpstan)
        log_info "Running PHPStan..."
        docker compose $COMPOSE_FILES exec php vendor/bin/phpstan analyse
        ;;

    fix)
        log_info "Fixing code style..."
        docker compose $COMPOSE_FILES exec php vendor/bin/php-cs-fixer fix
        ;;

    worker-logs)
        log_info "Test worker logs:"
        docker compose $COMPOSE_FILES logs -f --tail=100 test-worker
        ;;

    scheduler-logs)
        log_info "Scheduler logs:"
        docker compose $COMPOSE_FILES logs -f --tail=100 scheduler
        ;;

    build)
        log_info "Building local images..."
        docker compose $COMPOSE_FILES build
        log_info "Build complete."
        ;;

    help|*)
        echo "MATRE Local Development"
        echo ""
        echo "Usage: ./local.sh [command]"
        echo ""
        echo "Commands:"
        echo "  start           Start local environment (with migrations)"
        echo "  stop            Stop local environment"
        echo "  restart         Restart local environment"
        echo "  status          Show container status"
        echo "  logs [svc]      Follow logs (all or specific service)"
        echo "  worker-logs     Follow test worker logs"
        echo "  scheduler-logs  Follow scheduler logs"
        echo "  shell [svc]     Open shell (default: php)"
        echo "  console [cmd]   Run Symfony console command"
        echo "  migrate         Run database migrations"
        echo "  cache           Clear cache"
        echo "  test [args]     Run PHPUnit tests"
        echo "  phpstan         Run PHPStan analysis"
        echo "  fix             Fix code style (php-cs-fixer)"
        echo "  build           Build images"
        echo "  help            Show this help"
        ;;
esac
