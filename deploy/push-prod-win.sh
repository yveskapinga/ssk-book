#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST="${SSK_PROD_HOST:-root@187.77.177.248}"
SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=20)
AIGW_ROOT="$(cd "${ROOT}/../ai-gateway" && pwd)"

echo "==> Sync ssk-book code → ${HOST}:/opt/ssk-book"
tar czf - \
  --exclude=./.git \
  --exclude=./mobile \
  --exclude=./web/node_modules \
  --exclude=./api/vendor \
  --exclude=./api/var/cache \
  --exclude=./api/var/log \
  --exclude=./api/.env \
  --exclude=./api/.env.local \
  --exclude=./api/.env.prod.local \
  --exclude=./.env \
  --exclude=./.env.prod \
  --exclude=./.env.deploy \
  --exclude=./ssk-book.secrets.env \
  -C "$ROOT" . \
  | ssh "${SSH_OPTS[@]}" "$HOST" "mkdir -p /opt/ssk-book && tar xzf - -C /opt/ssk-book"

echo "==> Sync web dist"
tar czf - -C "${ROOT}/web/dist" . \
  | ssh "${SSH_OPTS[@]}" "$HOST" "mkdir -p /opt/ssk-book/web/dist && find /opt/ssk-book/web/dist -mindepth 1 -delete && tar xzf - -C /opt/ssk-book/web/dist"

echo "==> Sync ai-gateway (keep remote .env)"
tar czf - \
  --exclude=./.git \
  --exclude=./.env \
  --exclude=./control-plane/var \
  --exclude=./docs \
  -C "$AIGW_ROOT" . \
  | ssh "${SSH_OPTS[@]}" "$HOST" "mkdir -p /opt/ai-gateway && tar xzf - -C /opt/ai-gateway"

echo "==> Refresh ai-gateway .env from ssk-book .env.prod"
ssh "${SSH_OPTS[@]}" "$HOST" "umask 077; python3 /opt/ssk-book/deploy/write-aigw-env.py"

echo "==> Deploy ai-gateway"
ssh "${SSH_OPTS[@]}" "$HOST" "bash /opt/ai-gateway/deployments/compose/deploy-prod.sh"

echo "==> Deploy ssk-book"
ssh "${SSH_OPTS[@]}" "$HOST" "bash /opt/ssk-book/deploy/deploy-prod.sh"

echo "==> Origin smoke"
ssh "${SSH_OPTS[@]}" "$HOST" bash -s <<'EOF'
set -e
echo -n 'edge-health:'; curl -s -o /tmp/ssk-h.json -w '%{http_code}' http://127.0.0.1:18181/api/health; echo; cat /tmp/ssk-h.json; echo
echo -n 'origin-ssk:'; curl -sk -o /dev/null -w '%{http_code}\n' --resolve ssk-book.yabisoo.com:443:127.0.0.1 https://ssk-book.yabisoo.com/api/health
echo -n 'origin-monama:'; curl -sk -o /dev/null -w '%{http_code}\n' --resolve monama.yabisoo.com:443:127.0.0.1 https://monama.yabisoo.com/
echo -n 'origin-likonda:'; curl -sk -o /dev/null -w '%{http_code}\n' --resolve likonda.yabisoo.com:443:127.0.0.1 https://likonda.yabisoo.com/
echo -n 'origin-yabisoo:'; curl -sk -o /dev/null -w '%{http_code}\n' --resolve yabisoo.com:443:127.0.0.1 https://yabisoo.com/
docker ps --format '{{.Names}} {{.Status}}' | grep -E 'ssk-book|ai-gateway|aigateway' || true
EOF

echo "Done."
