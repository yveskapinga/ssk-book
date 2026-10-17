#!/usr/bin/env bash
set -euo pipefail

# SSK Book production — ssk-book.yabisoo.com on 187.77.177.248
# Usage (on server): sudo bash /opt/ssk-book/deploy/deploy-prod.sh
#
# Never writes into yabisoo / likonda / ulizo / monama paths, vhosts, or docker projects.

DOMAIN="ssk-book.yabisoo.com"
EMAIL="yveskapinga@gmail.com"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP_ROOT="/opt/ssk-book"
COMPOSE_FILE="${ROOT}/deploy/compose.prod.yaml"
ENV_FILE="${APP_ROOT}/.env.prod"
DUMP_FILE="${APP_ROOT}/deploy/data/ssk_book.dump"
NGINX_AVAIL="/etc/nginx/sites-available/${DOMAIN}"
NGINX_ENABLED="/etc/nginx/sites-enabled/${DOMAIN}"
EDGE_PORT="${SSK_EDGE_PORT:-18181}"
PG_PORT="${SSK_PG_PORT:-55440}"

FORBIDDEN_PATHS=(
  /etc/nginx/sites-available/yabisoo
  /etc/nginx/sites-enabled/yabisoo
  /etc/nginx/sites-available/likonda
  /etc/nginx/sites-enabled/likonda
  /etc/nginx/sites-available/likonda.yabisoo.com
  /etc/nginx/sites-enabled/likonda.yabisoo.com
  /etc/nginx/sites-available/ulizo
  /etc/nginx/sites-enabled/ulizo
  /etc/nginx/sites-available/partners-yabisoo.conf
  /etc/nginx/sites-enabled/partners-yabisoo.conf
  /etc/nginx/sites-available/monama.yabisoo.com
  /etc/nginx/sites-enabled/monama.yabisoo.com
)

FORBIDDEN_TREES=(
  /var/www/yabisoo
  /var/www/likonda
  /var/www/ulizo
  /var/www/monama
  /opt/monama
)

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Relancer avec sudo : sudo bash $0" >&2
  exit 1
fi

if [[ "$ROOT" != "$APP_ROOT" ]]; then
  echo "Ce script doit vivre dans ${APP_ROOT} (trouvé : ${ROOT})." >&2
  exit 1
fi

die() { echo "ERREUR: $*" >&2; exit 1; }

assert_isolated() {
  local p
  for p in "${FORBIDDEN_PATHS[@]}" "${FORBIDDEN_TREES[@]}"; do
    if [[ "$APP_ROOT" == "$p" || "$APP_ROOT" == "$p"/* ]]; then
      die "chemin ssk-book en collision avec $p"
    fi
  done
}

snapshot_forbidden() {
  local p
  for p in "${FORBIDDEN_PATHS[@]}"; do
    if [[ -L "$p" || -f "$p" ]]; then
      readlink -f "$p" 2>/dev/null || true
      sha256sum "$p" 2>/dev/null || true
    else
      echo "missing $p"
    fi
  done
  docker ps --format '{{.Names}}' | sort || true
}

port_busy_foreign() {
  local port="$1" own_re="$2"
  if ! ss -ltn | awk '{print $4}' | grep -E ":${port}$" >/dev/null; then
    return 1
  fi
  if docker ps --format '{{.Names}}' | grep -Eq "$own_re"; then
    return 1
  fi
  return 0
}

assert_isolated

echo "=== SSK Book prod ${DOMAIN} ==="
echo "APP ${APP_ROOT}"

BEFORE="$(snapshot_forbidden)"

[[ -f "$ENV_FILE" ]] || die "missing ${ENV_FILE}"
# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a
[[ -n "${POSTGRES_PASSWORD:-}" ]] || die "POSTGRES_PASSWORD manquant dans ${ENV_FILE}"
[[ -n "${APP_SECRET:-}" ]] || die "APP_SECRET manquant dans ${ENV_FILE}"

install -d -m 0755 \
  "${APP_ROOT}/storage/books" \
  "${APP_ROOT}/web/dist" \
  "${APP_ROOT}/deploy/data" \
  /var/www/letsencrypt \
  /var/log/nginx

if ! command -v docker >/dev/null 2>&1; then
  die "docker absent — installer docker manuellement sans toucher aux stacks existantes"
fi
if ! command -v nginx >/dev/null 2>&1; then
  die "nginx absent — ne pas installer automatiquement"
fi

if port_busy_foreign "$PG_PORT" '^ssk-book-postgres$'; then
  die "port ${PG_PORT} déjà pris par un autre service"
fi
if port_busy_foreign "$EDGE_PORT" '^ssk-book-edge$'; then
  die "port ${EDGE_PORT} déjà pris par un autre service"
fi

echo "==> Docker network ssk_net"
docker network create ssk_net >/dev/null 2>&1 || true

echo "==> Compose up ssk-book"
docker compose -p ssk-book --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --build

echo "==> Wait postgres"
for i in $(seq 1 60); do
  if docker compose -p ssk-book -f "$COMPOSE_FILE" exec -T postgres \
    pg_isready -U "${POSTGRES_USER:-ssk_book}" -d "${POSTGRES_DB:-ssk_book}" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
docker compose -p ssk-book -f "$COMPOSE_FILE" exec -T postgres \
  pg_isready -U "${POSTGRES_USER:-ssk_book}" -d "${POSTGRES_DB:-ssk_book}"

if [[ -f "$DUMP_FILE" ]]; then
  echo "==> Restore database dump"
  docker compose -p ssk-book -f "$COMPOSE_FILE" exec -T postgres \
    pg_restore -U "${POSTGRES_USER:-ssk_book}" -d "${POSTGRES_DB:-ssk_book}" --clean --if-exists --no-owner --no-acl \
    < "$DUMP_FILE" || true
fi

echo "==> TLS material (self-signed until DNS + certbot succeed)"
install -d -m 0755 /etc/ssl/ssk-book
if [[ ! -f "/etc/ssl/ssk-book/${DOMAIN}.crt" ]]; then
  openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
    -keyout "/etc/ssl/ssk-book/${DOMAIN}.key" \
    -out "/etc/ssl/ssk-book/${DOMAIN}.crt" \
    -subj "/CN=${DOMAIN}" >/dev/null 2>&1
  chmod 640 "/etc/ssl/ssk-book/${DOMAIN}.key"
fi

echo "==> Nginx vhost ${DOMAIN}"
# Keep HTTP vhost up for ACME; HTTPS uses self-signed first (Cloudflare Full OK).
install -m 0644 "${ROOT}/deploy/nginx/ssk-book.yabisoo.com.http.conf" "${NGINX_AVAIL}.http" 2>/dev/null || true
install -m 0644 "${ROOT}/deploy/nginx/ssk-book.yabisoo.com.conf" "$NGINX_AVAIL"
ln -sfn "$NGINX_AVAIL" "$NGINX_ENABLED"

if command -v certbot >/dev/null 2>&1 && [[ ! -d "/etc/letsencrypt/live/${DOMAIN}" ]]; then
  # Optional: only works once public DNS points here. Never abort the deploy.
  certbot certonly --webroot -w /var/www/letsencrypt \
    -d "$DOMAIN" --email "$EMAIL" --agree-tos --non-interactive --keep-until-expiring \
    || echo "certbot skipped/failed — self-signed kept (configure DNS then re-run)"
fi
if [[ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]]; then
  sed -i "s#ssl_certificate /etc/ssl/ssk-book/${DOMAIN}.crt;#ssl_certificate /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;#" "$NGINX_AVAIL"
  sed -i "s#ssl_certificate_key /etc/ssl/ssk-book/${DOMAIN}.key;#ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;#" "$NGINX_AVAIL"
fi

if nginx -t; then
  systemctl reload nginx
else
  rm -f "$NGINX_ENABLED"
  die "nginx -t a échoué — vhost ssk-book retiré, yabisoo/likonda/monama inchangés"
fi

echo "==> Migrations"
docker compose -p ssk-book -f "$COMPOSE_FILE" exec -T api \
  php bin/console doctrine:migrations:migrate --no-interaction --env=prod || true

echo "==> Local smoke"
sleep 2
curl -fsS "http://127.0.0.1:${EDGE_PORT}/api/health" | tee /tmp/ssk-book-health.json
echo
curl -fsS -o /dev/null -w 'edge-root:%{http_code}\n' "http://127.0.0.1:${EDGE_PORT}/"
curl -sk -o /dev/null -w "origin-${DOMAIN}:%{http_code}\n" --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/api/health"

echo "==> Non-regression checks"
for host in monama.yabisoo.com yabisoo.com likonda.yabisoo.com ulizo.yabisoo.com; do
  code="$(curl -sk -o /dev/null -w '%{http_code}' --resolve "${host}:443:127.0.0.1" "https://${host}/" || echo ERR)"
  echo "origin-${host}:${code}"
done

AFTER="$(snapshot_forbidden)"
if [[ "$BEFORE" != "$AFTER" ]]; then
  # Container list will change (ssk-book added) — only fail if forbidden vhost checksums changed.
  before_paths="$(printf '%s\n' "$BEFORE" | grep -E 'sites-|missing ' || true)"
  after_paths="$(printf '%s\n' "$AFTER" | grep -E 'sites-|missing ' || true)"
  if [[ "$before_paths" != "$after_paths" ]]; then
    echo "ATTENTION: snapshot vhosts interdits a changé" >&2
    diff -u <(printf '%s\n' "$before_paths") <(printf '%s\n' "$after_paths") || true
    die "isolation compromise"
  fi
fi

echo "OK — https://${DOMAIN}"
