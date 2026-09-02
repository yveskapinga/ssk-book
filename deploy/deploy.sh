#!/usr/bin/env bash
# Deploy ssk-book → https://ssk-book.solutic.app
# Usage: bash deploy/deploy.sh
set -euo pipefail

DOMAIN="ssk-book.solutic.app"
EMAIL="yveskapinga@gmail.com"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
API="${ROOT}/api"
WEB="${ROOT}/web"
WWW="/var/www/test/ssk-book"
LOG_DIR="/var/www/test/_runtime/logs"
ADMIN_FILE="/var/www/test/_runtime/ssk-book-admin.txt"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.3}"
PG_PORT=55440
API_PORT=18180
COMPOSE_FILE="${ROOT}/deploy/compose.postgres.yaml"
ENV_FILE="${ROOT}/.env.deploy"
API_ENV="${API}/.env.prod.local"

echo "==> ssk-book deploy → https://${DOMAIN}"
echo "    source: ${ROOT} ($(cd "${ROOT}" && git rev-parse --short HEAD))"

install -d -m 0755 "${LOG_DIR}" "${WWW}" "${ROOT}/storage/books" "${API}/var/books"
install -d -m 0755 /var/lib/.local-state/yves/.local/bin

if [[ ! -f "${ENV_FILE}" ]]; then
  umask 077
  cat > "${ENV_FILE}" <<EOF
POSTGRES_DB=ssk_book
POSTGRES_USER=ssk_book
POSTGRES_PASSWORD=$(openssl rand -hex 18)
EOF
  echo "==> Created ${ENV_FILE}"
fi
# shellcheck disable=SC1090
source "${ENV_FILE}"

if [[ ! -f "${API_ENV}" ]]; then
  umask 077
  cat > "${API_ENV}" <<EOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$(openssl rand -hex 32)
DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@127.0.0.1:${PG_PORT}/${POSTGRES_DB}?serverVersion=16&charset=utf8
CORS_ALLOW_ORIGIN=^https://ssk-book\\.solutic\\.app\$
GEMINI_API_KEY=
GEMINI_GENERATION_MODEL=gemini-flash-lite-latest
GEMINI_EMBEDDING_MODEL=gemini-embedding-001
GEMINI_EMBEDDING_DIMENSION=768
EOF
  echo "==> Created ${API_ENV}"
fi

echo "==> Postgres :${PG_PORT}"
docker compose -p ssk-book --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" up -d
for i in $(seq 1 60); do
  if docker compose -p ssk-book -f "${COMPOSE_FILE}" exec -T postgres pg_isready -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
docker compose -p ssk-book -f "${COMPOSE_FILE}" exec -T postgres pg_isready -U "${POSTGRES_USER}" -d "${POSTGRES_DB}"

echo "==> pdftotext / pdftoppm / pdfimages / pdftohtml wrappers"
install -m 0755 "${ROOT}/deploy/pdftotext" /var/lib/.local-state/yves/.local/bin/pdftotext
install -m 0755 "${ROOT}/deploy/pdftoppm" /var/lib/.local-state/yves/.local/bin/pdftoppm
install -m 0755 "${ROOT}/deploy/pdfimages" /var/lib/.local-state/yves/.local/bin/pdfimages
install -m 0755 "${ROOT}/deploy/pdftohtml" /var/lib/.local-state/yves/.local/bin/pdftohtml
cp "${ROOT}/deploy/router.php" "${API}/router.php"

echo "==> Composer"
COMPOSER_BIN="$(command -v composer)"
(cd "${API}" && COMPOSER_NO_SECURITY_BLOCKING=1 "${PHP_BIN}" "${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-interaction --no-scripts)
(cd "${API}" && APP_ENV=prod APP_DEBUG=0 "${PHP_BIN}" bin/console cache:clear --env=prod --no-debug --no-warmup)
(cd "${API}" && APP_ENV=prod APP_DEBUG=0 "${PHP_BIN}" bin/console cache:warmup --env=prod --no-debug)

echo "==> Migrations"
(cd "${API}" && APP_ENV=prod APP_DEBUG=0 "${PHP_BIN}" bin/console doctrine:migrations:migrate --no-interaction --env=prod)

if [[ ! -f "${ADMIN_FILE}" ]]; then
  echo "==> Admin account"
  ADMIN_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)Aa1"
  umask 077
  if (cd "${API}" && APP_ENV=prod APP_DEBUG=0 "${PHP_BIN}" bin/console app:admin:create yveskapinga@gmail.com "Yves Kapinga" --password="${ADMIN_PASS}" --env=prod); then
    cat > "${ADMIN_FILE}" <<EOF
email=yveskapinga@gmail.com
password=${ADMIN_PASS}
url=https://${DOMAIN}/connexion
EOF
  else
    echo "    admin already present or create failed — continuing"
  fi
fi

echo "==> Frontend"
umask 022
(cd "${WEB}" && [[ -d node_modules ]] || npm ci)
(cd "${WEB}" && VITE_API_BASE_URL= npm run build)
rsync -a --delete "${WEB}/dist/" "${WWW}/"
chmod -R u=rwX,go=rX "${WWW}"

echo "==> PM2"
pm2 delete ssk-book-api >/dev/null 2>&1 || true
pm2 start "${ROOT}/deploy/ecosystem.config.cjs"
pm2 save || true

echo "==> Local smoke"
sleep 2
curl -fsS "http://127.0.0.1:${API_PORT}/api/health"
echo

echo "Build OK. Install nginx + TLS next (docker, no sudo):"
echo "  bash ${ROOT}/deploy/install-vhost-cert.sh"
