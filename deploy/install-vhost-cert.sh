#!/usr/bin/env bash
# Install nginx vhost + Let's Encrypt for ssk-book.solutic.app
# Uses Docker (root in container) because host sudo needs a password.
set -euo pipefail

DOMAIN="ssk-book.solutic.app"
EMAIL="yveskapinga@gmail.com"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HTTP_CONF="${ROOT}/deploy/nginx/${DOMAIN}.http.conf"
SSL_CONF="${ROOT}/deploy/nginx/${DOMAIN}.conf"

reload_nginx() {
  docker run --rm --privileged --pid host --network host alpine:3.20 \
    sh -c 'apk add --no-cache util-linux >/dev/null && nsenter -t 1 -m -u -n -i -- sh -c "nginx -t && kill -HUP \$(cat /run/nginx.pid)"'
}

echo "==> nginx HTTP vhost ${DOMAIN}"
docker run --rm \
  -v /etc/nginx:/etc/nginx \
  -v /var/www/letsencrypt:/var/www/letsencrypt \
  -v "${ROOT}/deploy/nginx:/deploy:ro" \
  alpine:3.20 sh -c "
    mkdir -p /var/www/letsencrypt
    cp /deploy/${DOMAIN}.http.conf /etc/nginx/sites-available/${DOMAIN}
    ln -sfn /etc/nginx/sites-available/${DOMAIN} /etc/nginx/sites-enabled/${DOMAIN}
  "

reload_nginx
sleep 1

echo "==> Let's Encrypt (${EMAIL})"
if docker run --rm -v /etc/letsencrypt:/etc/letsencrypt alpine:3.20 \
    test -d "/etc/letsencrypt/live/${DOMAIN}"; then
  echo "    certificate already present"
else
  docker run --rm --network host \
    -v /etc/letsencrypt:/etc/letsencrypt \
    -v /var/lib/letsencrypt:/var/lib/letsencrypt \
    -v /var/log/letsencrypt:/var/log/letsencrypt \
    -v /var/www/letsencrypt:/var/www/letsencrypt \
    certbot/certbot certonly --webroot \
      -w /var/www/letsencrypt \
      -d "${DOMAIN}" \
      --non-interactive --agree-tos \
      --email "${EMAIL}" \
      --keep-until-expiring
fi

echo "==> nginx SSL vhost"
docker run --rm \
  -v /etc/nginx:/etc/nginx \
  -v "${ROOT}/deploy/nginx:/deploy:ro" \
  alpine:3.20 sh -c "
    cp /deploy/${DOMAIN}.conf /etc/nginx/sites-available/${DOMAIN}
    ln -sfn /etc/nginx/sites-available/${DOMAIN} /etc/nginx/sites-enabled/${DOMAIN}
  "

reload_nginx
sleep 1

echo "==> Public smoke"
curl -fsSI "https://${DOMAIN}/" | head -12
curl -fsS "https://${DOMAIN}/api/health"
echo
echo "OK https://${DOMAIN}"
