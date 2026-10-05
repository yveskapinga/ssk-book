#!/usr/bin/env bash
set -euo pipefail
HOST="${SSK_PROD_HOST:-root@187.77.177.248}"
SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=20)
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "==> Sync API reading pack files"
tar czf - \
  -C "$ROOT" \
  api/src/Book/BookRepository.php \
  api/src/Book/ReadingService.php \
  api/src/Controller/LibraryController.php \
  | ssh "${SSH_OPTS[@]}" "$HOST" "tar xzf - -C /opt/ssk-book"

echo "==> Rebuild API container"
ssh "${SSH_OPTS[@]}" "$HOST" \
  "cd /opt/ssk-book && docker compose -p ssk-book --env-file .env.prod -f deploy/compose.prod.yaml up -d --build api"

echo "==> Smoke pack route (401/403/404 expected without auth)"
ssh "${SSH_OPTS[@]}" "$HOST" \
  'code=$(curl -s -o /tmp/pack.json -w "%{http_code}" http://127.0.0.1:18181/api/books/x/reading/pack); echo "http:$code"; head -c 240 /tmp/pack.json; echo'

echo "Done."
