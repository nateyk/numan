#!/bin/bash
# NumanFreight cPanel deployment.
#
# Run on the cPanel terminal:  bash deploy-cpanel.sh [--seed]
#
# Seeding is opt-in only. This script will not create demo data on its own, ever.
# Demo data means a superadmin whose password is published in the repository, and
# "the freight table happened to be empty" is not a good enough reason to create
# one on a live site.

set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/numan}"
BACKUP_DIR="${HOME}/backups/$(date +%Y%m%d_%H%M%S)"
SEED=0
WANT_SEED=0

if [[ "${1:-}" == "--seed" ]]; then
    WANT_SEED=1
fi

echo "==> Working in ${APP_DIR}"
cd "${APP_DIR}"

# ---------------------------------------------------------------------------
# 1. Refuse to deploy over uncommitted work
# ---------------------------------------------------------------------------
# git reset --hard below would discard it without asking. On a shared host that
# is how a config tweak made in a hurry disappears, and the person who made it
# finds out days later.
if [[ -n "$(git status --porcelain)" ]]; then
    echo ""
    echo "ABORT: the working tree has uncommitted changes."
    echo "This script runs 'git reset --hard', which would discard them."
    echo ""
    git status --short
    echo ""
    echo "Commit them, stash them, or set them elsewhere and re-run."
    exit 1
fi

# ---------------------------------------------------------------------------
# 2. Back up before anything is touched
# ---------------------------------------------------------------------------
echo "==> Backing up to ${BACKUP_DIR}"
mkdir -p "${BACKUP_DIR}"
cp .env "${BACKUP_DIR}/.env.backup" 2>/dev/null || true
tar czf "${BACKUP_DIR}/storage-private.tar.gz" storage/app/private 2>/dev/null || true
php artisan db:backup --database=mysql 2>/dev/null || echo "   (no db:backup command; back the database up out of band)"
echo "    ${BACKUP_DIR}"

# ---------------------------------------------------------------------------
# 3. Code and dependencies
# ---------------------------------------------------------------------------
echo "==> Updating code"
git fetch origin
git reset --hard origin/main
echo "    now at $(git rev-parse --short HEAD)"

echo "==> Installing PHP dependencies"
# No --no-scripts: skipping them leaves the package manifest unbuilt and every
# subsequent artisan command fails for an unrelated-looking reason.
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Only when it is genuinely absent. An unconditional key:generate --force here
# rotates APP_KEY on every deploy, which signs every user out, invalidates every
# Sanctum token, and makes every encrypted value unreadable -- including any
# manual-payment instructions token in flight at the time.
if ! grep -qE '^APP_KEY=base64:.+' .env; then
    echo "==> APP_KEY missing, generating one"
    php artisan key:generate --force
fi

echo "==> Building assets"
if command -v pnpm > /dev/null 2>&1; then
    pnpm install --frozen-lockfile
    pnpm run build
else
    npm ci
    npm run build
fi

# ---------------------------------------------------------------------------
# 4. Database
# ---------------------------------------------------------------------------
echo "==> Migrating"
php artisan migrate --force

# ---------------------------------------------------------------------------
# 5. Optional demo data, and only when asked for by name
# ---------------------------------------------------------------------------
if [[ "${WANT_SEED}" -eq 1 ]]; then
    echo "==> Seeding demo data (--seed given)"
    php artisan db:seed --class=DemoDataSeeder --force
    echo "    Demo accounts use the published demo password. Change them or"
    echo "    delete them before this site takes real traffic."
else
    echo "==> Skipping demo data (pass --seed to include it)"
fi

# ---------------------------------------------------------------------------
# 6. Caches, links, permissions
# ---------------------------------------------------------------------------
echo "==> Rebuilding caches"
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Linking storage"
php artisan storage:link

chmod -R 775 storage bootstrap/cache
chmod -R 775 public/storage 2>/dev/null || true

php artisan queue:restart 2>/dev/null || true

# ---------------------------------------------------------------------------
# 7. Verify
# ---------------------------------------------------------------------------
echo "==> Checking the site responds"
URL="$(grep -oE 'https?://[A-Za-z0-9._/-]+' .env | grep -i app_url | head -1 || true)"
URL="${URL:-$(php artisan tinker --execute='echo config("app.url");' 2>/dev/null | tail -1)}"

if [[ -n "${URL}" ]]; then
    CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "${URL}" || echo 000)"
    if [[ "${CODE}" == "200" ]]; then
        echo "    OK (200) ${URL}"
    else
        echo "    WARNING: ${URL} returned ${CODE}"
    fi
else
    echo "    Could not determine APP_URL; check it by hand."
fi

echo ""
echo "==> Deploy complete. Nothing is printed about accounts or passwords."
echo "    Logs:   tail -f storage/logs/laravel.log"
echo "    Verify: php artisan marketplace:install --dry-run"
