#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Load .env if it exists
if [ -f "$SCRIPT_DIR/.env" ]; then
    set -a
    # shellcheck disable=SC1091
    source "$SCRIPT_DIR/.env"
    set +a
fi

ENVIRONMENT="${1:-}"

if [ -z "$ENVIRONMENT" ]; then
    echo "Usage: ./deploy.sh <local|qnap>"
    exit 1
fi

deploy_local() {
    echo "Deploying locally..."

    cd "$SCRIPT_DIR"

    echo "Pulling latest code..."
    git pull origin main

    echo "Installing backend dependencies..."
    cd "$SCRIPT_DIR/backend"
    composer install --no-dev --optimize-autoloader

    echo "Running migrations..."
    php artisan migrate --force

    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    echo "Restarting Horizon..."
    php artisan horizon:terminate

    echo "Building frontend..."
    cd "$SCRIPT_DIR/frontend"
    npm ci
    npm run build

    echo "Local deploy complete."
}

deploy_qnap() {
    QNAP_HOST="${QNAP_HOST:?QNAP_HOST is not set in .env}"
    QNAP_USER="${QNAP_USER:?QNAP_USER is not set in .env}"
    QNAP_PROJECT_PATH="${QNAP_PROJECT_PATH:?QNAP_PROJECT_PATH is not set in .env}"
    QNAP_DOCKER="${QNAP_DOCKER_BINARY:?QNAP_DOCKER_BINARY is not set in .env}"

    echo "Deploying to QNAP at $QNAP_HOST..."

    ssh "$QNAP_USER@$QNAP_HOST" bash -s "$QNAP_PROJECT_PATH" "$QNAP_DOCKER" << 'REMOTE_SCRIPT'
        set -euo pipefail
        PROJECT_PATH="$1"
        DOCKER="$2"
        COMPOSE="$DOCKER compose"

        cd "$PROJECT_PATH"

        echo "Pulling latest code..."
        git pull origin main

        echo "Building containers..."
        $COMPOSE build

        echo "Starting services..."
        $COMPOSE up -d --remove-orphans

        echo "Running migrations..."
        $COMPOSE exec -T frankenphp php artisan migrate --force

        echo "Caching configuration..."
        $COMPOSE exec -T frankenphp php artisan config:cache
        $COMPOSE exec -T frankenphp php artisan route:cache
        $COMPOSE exec -T frankenphp php artisan view:cache
        $COMPOSE exec -T frankenphp php artisan event:cache

        echo "Restarting Horizon..."
        $COMPOSE exec -T frankenphp php artisan horizon:terminate

        echo "QNAP deploy complete."
REMOTE_SCRIPT
}

case "$ENVIRONMENT" in
    local)
        deploy_local
        ;;
    qnap)
        deploy_qnap
        ;;
    *)
        echo "Unknown environment: $ENVIRONMENT"
        echo "Usage: ./deploy.sh <local|qnap>"
        exit 1
        ;;
esac
