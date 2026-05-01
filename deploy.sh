#!/usr/bin/env bash
set -euo pipefail

# ─────────────────────────────────────────────────────────
#  UTG Transport — VPS Deploy Script
#  Ishlatish: bash deploy.sh [fresh|update|logs|down|status]
# ─────────────────────────────────────────────────────────

COMPOSE="docker compose"
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'

info()    { echo -e "${GREEN}[INFO]${NC} $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

CMD="${1:-fresh}"

# ── Check .env ────────────────────────────────────────────
check_env() {
    if [ ! -f .env ]; then
        warn ".env topilmadi. .env.example dan nusxa olinyapti..."
        cp .env.example .env
        warn ".env faylini to'ldiring va qayta ishga tushiring!"
        exit 1
    fi
    source .env
}

# ── Generate Laravel key ──────────────────────────────────
generate_key() {
    info "Laravel APP_KEY generatsiya qilinmoqda..."
    KEY=$(docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));")
    # Replace placeholder OR any existing base64: key
    sed -i "s|APP_KEY=.*|APP_KEY=${KEY}|g" backend/.env.docker
    info "APP_KEY o'rnatildi."
}

# ── Fresh install ─────────────────────────────────────────
cmd_fresh() {
    check_env
    info "DB credentials va APP_KEY sozlanmoqda (konteynerlar ishga tushirishdan oldin)..."
    sed -i "s|DB_PASSWORD=utg_pass|DB_PASSWORD=${DB_PASSWORD}|g" backend/.env.docker
    sed -i "s|DB_USERNAME=utg|DB_USERNAME=${DB_USERNAME}|g" backend/.env.docker
    sed -i "s|DB_DATABASE=utg_transport|DB_DATABASE=${DB_DATABASE}|g" backend/.env.docker
    generate_key

    info "Docker images build qilinmoqda..."
    $COMPOSE build --no-cache --parallel

    info "MySQL ishga tushirilmoqda (tayyor bo'lishini kutish)..."
    $COMPOSE up -d db

    info "Konteynerlar ishga tushirilmoqda..."
    $COMPOSE up -d backend backend_nginx queue frontend

    sleep 5

    info "Migratsiyalar ishga tushirilmoqda..."
    $COMPOSE exec backend php artisan migrate --force

    info "Seeder ishga tushirilmoqda..."
    $COMPOSE exec backend php artisan db:seed --force

    info "Cache optimallashtirilyapti..."
    $COMPOSE exec backend php artisan config:cache
    $COMPOSE exec backend php artisan route:cache
    $COMPOSE exec backend php artisan view:cache

    info "Storage link yaratilmoqda..."
    $COMPOSE exec backend php artisan storage:link || true

    info ""
    info "✅ O'rnatish muvaffaqiyatli yakunlandi!"
    cmd_status
}

# ── Update (pull + rebuild) ───────────────────────────────
cmd_update() {
    info "Kod yangilanmoqda (git pull)..."
    git pull

    info "Docker images rebuild qilinmoqda..."
    $COMPOSE build --parallel

    info "Konteynerlar yangilanmoqda..."
    $COMPOSE up -d --no-deps backend backend_nginx queue frontend

    sleep 5

    info "Migratsiyalar..."
    $COMPOSE exec backend php artisan migrate --force

    info "Cache yangilanmoqda..."
    $COMPOSE exec backend php artisan config:cache
    $COMPOSE exec backend php artisan route:cache
    $COMPOSE exec backend php artisan view:cache

    info "✅ Yangilash yakunlandi!"
    cmd_status
}

# ── Logs ──────────────────────────────────────────────────
cmd_logs() {
    SERVICE="${2:-backend}"
    $COMPOSE logs -f --tail=100 "$SERVICE"
}

# ── Status ────────────────────────────────────────────────
cmd_status() {
    echo ""
    $COMPOSE ps
    echo ""
    info "Frontend:  http://$(hostname -I | awk '{print $1}'):${FRONTEND_PORT:-80}"
    info "Backend:   http://$(hostname -I | awk '{print $1}'):${BACKEND_PORT:-8000}"
}

# ── Down ──────────────────────────────────────────────────
cmd_down() {
    warn "Barcha konteynerlar to'xtatilmoqda..."
    $COMPOSE down
}

# ── Dispatch ──────────────────────────────────────────────
case "$CMD" in
    fresh)   cmd_fresh   ;;
    update)  cmd_update  ;;
    logs)    cmd_logs "$@" ;;
    status)  cmd_status  ;;
    down)    cmd_down    ;;
    *)       echo "Ishlatish: $0 {fresh|update|logs|down|status}"; exit 1 ;;
esac
