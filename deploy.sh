#!/usr/bin/env bash

# ============================================================
# deploy.sh — chedonse-racks Docker deployment script
#
# Usage:
#   ./deploy.sh            First-time setup or rolling update
#   ./deploy.sh --fresh    Wipe DB and reseed (destructive)
# ============================================================

set -euo pipefail

# ── Colours ──────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; BOLD='\033[1m'; NC='\033[0m'

info()    { echo -e "${BLUE}▶  $*${NC}"; }
success() { echo -e "${GREEN}✔  $*${NC}"; }
warn()    { echo -e "${YELLOW}⚠  $*${NC}"; }
die()     { echo -e "${RED}✖  $*${NC}"; exit 1; }
hr()      { echo -e "${BOLD}────────────────────────────────────────${NC}"; }

# ── Flags ─────────────────────────────────────────────────────
FRESH=false
for arg in "$@"; do
    case $arg in
        --fresh) FRESH=true ;;
        *) die "Unknown argument: $arg" ;;
    esac
done

SAIL="./vendor/bin/sail"
didComposerInstall=false
APP_SERVICE_NAME="${APP_SERVICE_NAME:-${APP_SERVICE:-laravel.test}}"

# ── 1. Prerequisites ──────────────────────────────────────────
hr
info "Checking prerequisites..."

command -v docker &>/dev/null      || die "Docker is not installed."
docker info &>/dev/null 2>&1       || die "Docker daemon is not running. Try: sudo systemctl start docker"
[[ -f "composer.json" ]]           || die "Run this script from the project root."
[[ -f "$SAIL" ]]                   || die "Sail not found. Run: composer install --no-dev"
command -v composer &>/dev/null    || die "Composer is not installed."

if [[ ! -f "vendor/autoload.php" ]] || ! php -r "require 'vendor/autoload.php'; exit(class_exists('Pusher\\\\Pusher') ? 0 : 1);" >/dev/null 2>&1; then
    warn "Broadcasting dependency is missing (Pusher\\Pusher). Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
    didComposerInstall=true
    success "Composer dependencies installed."
fi

export WWWUSER="${WWWUSER:-$(id -u)}"
export WWWGROUP="${WWWGROUP:-$(id -g)}"
success "Docker OK  (WWWUSER=${WWWUSER}, WWWGROUP=${WWWGROUP})"

# ── 2. .env ───────────────────────────────────────────────────
hr
FIRST_RUN=false

ensure_env_key() {
    local key="$1"
    local defaultValue="$2"

    if ! grep -q "^${key}=" .env; then
        echo "${key}=${defaultValue}" >> .env
        warn "Added missing ${key} to .env (default: ${defaultValue})"
    fi
}

service_is_running() {
    local service="$1"

    docker compose ps --services --filter status=running | grep -qx "$service"
}

wait_for_service_running() {
    local service="$1"
    local timeoutSeconds="${2:-120}"
    local elapsed=0

    while ! service_is_running "$service"; do
        if (( elapsed >= timeoutSeconds )); then
            return 1
        fi

        sleep 2
        elapsed=$((elapsed + 2))
    done

    return 0
}

ensure_service_running() {
    local service="$1"
    local timeoutSeconds="${2:-120}"

    if service_is_running "$service"; then
        return 0
    fi

    warn "${service} is not running. Attempting to start it..."

    if ! $SAIL up -d "$service" >/dev/null 2>&1; then
        warn "Direct start for ${service} failed. Retrying full stack startup..."
        $SAIL up -d >/dev/null 2>&1 || true
    fi

    if ! wait_for_service_running "$service" "$timeoutSeconds"; then
        warn "Recent logs for ${service}:"
        docker compose logs --tail=120 "$service" | cat || true
        die "${service} failed to reach running state."
    fi

    success "${service} is running."
}

if [[ ! -f ".env" ]]; then
    info "No .env found — copying from .env.example..."
    cp .env.example .env
    FIRST_RUN=true
    warn "Review .env and re-run the script if you need custom values."
fi

# Generate APP_KEY if blank
if ! grep -q '^APP_KEY=base64:' .env; then
    info "Generating APP_KEY..."
    php artisan key:generate --ansi
fi

info "Ensuring OCR deployment variables exist in .env..."
ensure_env_key "OCR_WEBHOOK_SECRET" ""
ensure_env_key "OCR_DEFAULT_LANGUAGE" "eng+fil+ceb"
ensure_env_key "OCR_PROCESS_TIMEOUT" "300"
ensure_env_key "OCR_PDF_RENDER_TIMEOUT" "300"
ensure_env_key "OCR_PDF_DPI" "300"
ensure_env_key "OCR_OEM" "3"
ensure_env_key "OCR_PSM" "3"
ensure_env_key "OCR_FALLBACK_PSM" "6,11"
ensure_env_key "OCR_PREPROCESS_ENABLED" "true"
ensure_env_key "OCR_PREPROCESS_MIN_WIDTH" "1800"
ensure_env_key "SUPERVISOR_OCR_QUEUE" "ocr"
ensure_env_key "SUPERVISOR_OCR_SLEEP" "5"
ensure_env_key "SUPERVISOR_OCR_TRIES" "2"
ensure_env_key "SUPERVISOR_OCR_MAX_TIME" "3600"
ensure_env_key "SUPERVISOR_OCR_TIMEOUT" "900"
ensure_env_key "SUPERVISOR_OCR_MEMORY" "1024"
ensure_env_key "SUPERVISOR_OCR_NUMPROCS" "1"

# ── 4. Containers ─────────────────────────────────────────────
hr
if [[ "$FIRST_RUN" == "true" ]]; then
    info "First-time setup — building image and starting containers..."
    $SAIL up -d --build
else
    info "Rebuilding image and restarting containers..."
    $SAIL up -d --build
fi
success "Containers started."

# Give Redis a moment to be healthy before running artisan
info "Waiting for services to be ready..."
sleep 5

ensure_service_running "mysql" 120
ensure_service_running "redis" 120
ensure_service_running "$APP_SERVICE_NAME" 180

# ── 5. Database ───────────────────────────────────────────────
hr
if [[ "$FRESH" == "true" ]]; then
    warn "--fresh requested: wiping the database and reseeding..."
    $SAIL artisan migrate:fresh --force
else
    info "Running migrations..."
    $SAIL artisan migrate --force
fi
success "Database migrations complete."

info "Seeding superadmin account..."
$SAIL artisan db:seed --class=SuperAdminSeeder
success "Superadmin ready."

info "Seeding Positions..."
$SAIL artisan db:seed --class=PositionSeeder
success "Positions seeded."

info "Seeding Divisions..."
$SAIL artisan db:seed --class=DepartmentSeeder
success "Divisions seeded."

info "Seeding Units..."
$SAIL artisan db:seed --class=UnitSeeder
success "Units seeded."

# ── 6. Frontend assets ────────────────────────────────────────
hr
info "Installing Node dependencies and building assets..."
$SAIL npm ci

# Fix permissions for wayfinder-generated directories
info "Ensuring proper permissions for generated TypeScript files..."
$SAIL exec laravel.test chown -R sail:sail /var/www/html/resources/js/actions /var/www/html/resources/js/routes 2>/dev/null || true

$SAIL npm run build
success "Frontend assets built."

# ── 7. Cache warm-up ──────────────────────────────────────────
hr
info "Warming up application caches..."
if [[ "$didComposerInstall" == "true" ]]; then
    info "Clearing stale caches after Composer install..."
    $SAIL artisan optimize:clear
fi
$SAIL artisan config:cache
$SAIL artisan route:cache
$SAIL artisan view:cache
success "Caches warmed."

# ── 8. Done ───────────────────────────────────────────────────
hr
APP_URL=$(     grep '^APP_URL='           .env | cut -d'=' -f2-)
SA_EMAIL=$(    grep '^SUPERADMIN_EMAIL='  .env | cut -d'=' -f2- || echo 'superadmin@example.com')
SA_PASSWORD=$( grep '^SUPERADMIN_PASSWORD=' .env | cut -d'=' -f2- || echo 'P@ssw0rd123')

echo -e "${GREEN}${BOLD}"
echo "  ✔  Deployment complete!"
echo ""
echo "     App URL  : ${APP_URL}"
echo "     Email    : ${SA_EMAIL}"
echo "     Password : ${SA_PASSWORD}"
echo -e "${NC}"

$SAIL ps
