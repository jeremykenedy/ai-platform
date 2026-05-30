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

    # Source VITE_REVERB_* / VITE_APP_NAME from the project .env so the
    # built bundle's Echo client uses the same Reverb key that the
    # backend container exposes. A missing key produces a bundle that
    # gets rejected by Reverb with "Application does not exist" and
    # every WebSocket subscription closes immediately — chat streaming
    # silently breaks while everything else looks fine.
    set -a
    source "$SCRIPT_DIR/.env"
    set +a
    : "${REVERB_APP_KEY:?REVERB_APP_KEY missing from .env}"
    VITE_REVERB_APP_KEY="$REVERB_APP_KEY" \
    VITE_REVERB_HOST="${VITE_REVERB_HOST:-${QNAP_HOST:-localhost}}" \
    VITE_REVERB_PORT="${VITE_REVERB_PORT:-${APP_HTTPS_PORT:-8443}}" \
    VITE_REVERB_SCHEME="${VITE_REVERB_SCHEME:-https}" \
    VITE_APP_NAME="${APP_NAME:-AI Platform}" \
    npm run build

    echo "Local deploy complete."
}

deploy_qnap() {
    QNAP_HOST="${QNAP_HOST:?QNAP_HOST is not set in .env}"
    QNAP_USER="${QNAP_USER:?QNAP_USER is not set in .env}"
    QNAP_PROJECT_PATH="${QNAP_PROJECT_PATH:?QNAP_PROJECT_PATH is not set in .env}"
    QNAP_DOCKER="${QNAP_DOCKER_BINARY:?QNAP_DOCKER_BINARY is not set in .env}"

    echo "Deploying to QNAP at $QNAP_HOST..."

    ssh "$QNAP_USER@$QNAP_HOST" bash -ls "$QNAP_PROJECT_PATH" "$QNAP_DOCKER" << 'REMOTE_SCRIPT'
        set -euo pipefail
        PROJECT_PATH="$1"
        DOCKER="$2"
        COMPOSE="$DOCKER compose"

        cd "$PROJECT_PATH"

        # Safety: if a leftover docker-compose.override.yml exists in prod it
        # would bind-mount ./backend over /app and wipe the image's vendor.
        # Move it out of the way; dev users should use docker-compose.dev.yml.
        if [ -f docker-compose.override.yml ]; then
          echo "WARNING: docker-compose.override.yml present in production. Renaming to .yml.disabled."
          mv docker-compose.override.yml docker-compose.override.yml.disabled
        fi

        echo "Pulling latest code..."
        git pull origin main

        # On QNAP, install-php-extensions fails inside docker build
        # ("tar: Cannot change mode: Bad address" — kernel/seccomp incompat).
        # So we ONLY rebuild images that actually build successfully on
        # this kernel. The frankenphp image is reused as-is and new PHP
        # source is copied into the running containers below.
        echo "Building frontend image..."
        $COMPOSE build frontend

        echo "Starting services..."
        $COMPOSE up -d --remove-orphans

        echo "Patching backend source into running PHP containers..."
        for c in ai-platform-frankenphp-1 ai-platform-horizon-1 ai-platform-reverb-1; do
          $DOCKER cp "$PROJECT_PATH/backend/app/."                   "$c:/app/app/"
          $DOCKER cp "$PROJECT_PATH/backend/routes/."                "$c:/app/routes/"
          $DOCKER cp "$PROJECT_PATH/backend/config/."                "$c:/app/config/"
          $DOCKER cp "$PROJECT_PATH/backend/database/migrations/."   "$c:/app/database/migrations/"
          $DOCKER cp "$PROJECT_PATH/backend/database/seeders/."      "$c:/app/database/seeders/"
          $DOCKER cp "$PROJECT_PATH/backend/database/factories/."    "$c:/app/database/factories/"
        done

        # Clear bootstrap cache from any prior dev run (Pail, etc.) before
        # artisan tries to bootstrap with cached providers it cannot find.
        $DOCKER exec ai-platform-frankenphp-1 sh -c \
          'rm -f /app/bootstrap/cache/packages.php /app/bootstrap/cache/services.php /app/bootstrap/cache/config.php /app/bootstrap/cache/routes-v7.php /app/bootstrap/cache/events.php' || true

        echo "Running migrations..."
        $COMPOSE exec -T frankenphp php artisan migrate --force

        echo "Caching configuration..."
        $COMPOSE exec -T frankenphp php artisan config:cache
        $COMPOSE exec -T frankenphp php artisan route:cache
        $COMPOSE exec -T frankenphp php artisan event:cache

        echo "Refreshing model registry against Ollama..."
        $COMPOSE exec -T frankenphp php artisan models:sync || true

        echo "Restarting Horizon..."
        $COMPOSE exec -T frankenphp php artisan horizon:terminate || true

        # Frontend nginx caches DNS for upstream containers. Restart so it
        # picks up any new frankenphp IP from this recreate cycle.
        $DOCKER restart ai-platform-frontend-1 || true

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
