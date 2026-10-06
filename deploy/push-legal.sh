#!/usr/bin/env bash
set -euo pipefail
HOST="${SSK_PROD_HOST:-root@187.77.177.248}"
SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=20)
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "==> Build web"
(
  cd "${ROOT}/web"
  [[ -d node_modules ]] || npm ci
  VITE_API_BASE_URL= npm run build
)

echo "==> Sync API + web dist"
tar czf - \
  -C "$ROOT" \
  api/src/Identity/AuthService.php \
  api/src/Controller/AuthController.php \
  web/dist \
  | ssh "${SSH_OPTS[@]}" "$HOST" "tar xzf - -C /opt/ssk-book"

echo "==> Rebuild API"
ssh "${SSH_OPTS[@]}" "$HOST" \
  "cd /opt/ssk-book && docker compose -p ssk-book --env-file .env.prod -f deploy/compose.prod.yaml up -d --build api"

echo "==> Smoke legal pages"
ssh "${SSH_OPTS[@]}" "$HOST" bash -s <<'EOF'
set -e
for path in /legal/privacy /legal/terms /legal/delete-account; do
  code=$(curl -sk -o /dev/null -w '%{http_code}' --resolve ssk-book.yabisoo.com:443:127.0.0.1 "https://ssk-book.yabisoo.com${path}")
  echo "origin${path}:${code}"
done
curl -s -o /dev/null -w "edge-privacy:%{http_code}\n" http://127.0.0.1:18181/legal/privacy
EOF

echo "Done."
