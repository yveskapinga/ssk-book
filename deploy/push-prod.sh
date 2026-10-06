#!/usr/bin/env bash
set -euo pipefail

# From the build machine: rsync ssk-book + ai-gateway to 187, dump DB, run deploy-prod.
# Usage: bash deploy/push-prod.sh

HOST="${SSK_PROD_HOST:-root@187.77.177.248}"
REMOTE_ROOT="/opt/ssk-book"
REMOTE_AIGW="/opt/ai-gateway"
LOCAL_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
AIGW_ROOT=""
if [[ -d "${LOCAL_ROOT}/../ai-gateway" ]]; then
  AIGW_ROOT="$(cd "${LOCAL_ROOT}/../ai-gateway" && pwd)"
fi
SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=20)
RSYNC_RSH="ssh -o BatchMode=yes -o ConnectTimeout=20"
DOMAIN="ssk-book.yabisoo.com"

ssh "${SSH_OPTS[@]}" "$HOST" true || {
  echo "SSH impossible vers ${HOST}." >&2
  exit 1
}

echo "==> Ensure remote dirs"
ssh "${SSH_OPTS[@]}" "$HOST" "install -d -m 0755 ${REMOTE_ROOT} ${REMOTE_AIGW} ${REMOTE_ROOT}/storage/books ${REMOTE_ROOT}/deploy/data ${REMOTE_ROOT}/web/dist /var/log/ssk-book"

echo "==> Build frontend"
(
  cd "${LOCAL_ROOT}/web"
  [[ -d node_modules ]] || npm ci
  VITE_API_BASE_URL= npm run build
)

echo "==> Dump source Postgres (optional)"
DUMP_LOCAL="${LOCAL_ROOT}/deploy/data/ssk_book.dump"
install -d -m 0755 "${LOCAL_ROOT}/deploy/data"
if [[ "${SSK_SKIP_DB_DUMP:-0}" != "1" ]] && docker ps --format '{{.Names}}' 2>/dev/null | grep -qx 'ssk-book-postgres'; then
  docker exec ssk-book-postgres pg_dump -U ssk_book -d ssk_book -Fc -f /tmp/ssk_book.dump
  docker cp ssk-book-postgres:/tmp/ssk_book.dump "$DUMP_LOCAL"
  docker exec ssk-book-postgres rm -f /tmp/ssk_book.dump
  ls -lh "$DUMP_LOCAL"
else
  echo "    skip dump (no local ssk-book-postgres or SSK_SKIP_DB_DUMP=1)"
  DUMP_LOCAL=""
fi

echo "==> Prepare .env.prod (from local secrets, domain rewritten)"
SRC_ENV=""
for candidate in \
  "${LOCAL_ROOT}/ssk-book.secrets.env" \
  "${LOCAL_ROOT}/api/.env.prod.local" \
  "${LOCAL_ROOT}/.env.deploy"
do
  if [[ -f "$candidate" ]]; then
    SRC_ENV="$candidate"
    break
  fi
done
if [[ -z "$SRC_ENV" ]]; then
  if ssh "${SSH_OPTS[@]}" "$HOST" "test -f ${REMOTE_ROOT}/.env.prod"; then
    echo "    keep remote ${REMOTE_ROOT}/.env.prod (no local secrets)"
    SRC_ENV=""
  else
    echo "Aucun fichier secrets local trouvé et pas de .env.prod distant" >&2
    exit 1
  fi
fi

TMP_ENV=""
if [[ -n "$SRC_ENV" ]]; then
  umask 077
  TMP_ENV="$(mktemp)"
  # shellcheck disable=SC1090
  set -a
  source "${LOCAL_ROOT}/.env.deploy" 2>/dev/null || true
  set +a

  # Merge: start from secrets/prod local, override domain-specific keys.
  cp "$SRC_ENV" "$TMP_ENV"
  # Ensure postgres credentials from .env.deploy if present
  if [[ -f "${LOCAL_ROOT}/.env.deploy" ]]; then
    # shellcheck disable=SC1090
    source "${LOCAL_ROOT}/.env.deploy"
  fi

  python3 - "$TMP_ENV" <<'PY'
import os, re, sys
path = sys.argv[1]
with open(path, encoding="utf-8") as f:
    lines = f.read().splitlines()
vals = {}
order = []
for line in lines:
    s = line.strip()
    if not s or s.startswith("#") or "=" not in s:
        continue
    k, v = s.split("=", 1)
    k = k.strip()
    if k not in vals:
        order.append(k)
    vals[k] = v.strip().strip('"').strip("'")

pg_user = os.environ.get("POSTGRES_USER") or vals.get("POSTGRES_USER") or "ssk_book"
pg_pass = os.environ.get("POSTGRES_PASSWORD") or vals.get("POSTGRES_PASSWORD")
pg_db = os.environ.get("POSTGRES_DB") or vals.get("POSTGRES_DB") or "ssk_book"
if not pg_pass:
    raise SystemExit("POSTGRES_PASSWORD missing")

def setv(k, v):
    if k not in vals:
        order.append(k)
    vals[k] = v

setv("APP_ENV", "prod")
setv("APP_DEBUG", "0")
if not vals.get("APP_SECRET"):
    setv("APP_SECRET", os.urandom(32).hex())
setv("POSTGRES_DB", pg_db)
setv("POSTGRES_USER", pg_user)
setv("POSTGRES_PASSWORD", pg_pass)
setv("DATABASE_URL", f"postgresql://{pg_user}:{pg_pass}@postgres:5432/{pg_db}?serverVersion=16&charset=utf8")
setv("CORS_ALLOW_ORIGIN", r"^https://ssk-book\.yabisoo\.com$")
setv("DEFAULT_URI", "https://ssk-book.yabisoo.com")
setv("BOOK_STORAGE_PATH", "/app/var/books")
setv("AIGW_BASE_URL", "http://ai-gateway-api:18190")
setv("AIGW_APPLICATION", vals.get("AIGW_APPLICATION") or "APP_SSK_BOOK")
setv("AIGW_COMPLEXITY", vals.get("AIGW_COMPLEXITY") or "low")
if not vals.get("AIGW_API_KEY"):
    setv("AIGW_API_KEY", "dev_only_change_me")
setv("SSK_PG_PORT", "55440")
setv("SSK_EDGE_PORT", "18181")

with open(path, "w", encoding="utf-8") as f:
    for k in order:
        f.write(f"{k}={vals[k]}\n")
PY
fi

echo "==> Rsync ssk-book → ${HOST}:${REMOTE_ROOT}"
rsync -az --delete \
  --exclude '.git/' \
  --exclude '/.env' \
  --exclude '/.env.prod' \
  --exclude '/.env.deploy' \
  --exclude 'ssk-book.secrets.env' \
  --exclude 'api/.env' \
  --exclude 'api/.env.local' \
  --exclude 'api/.env.prod.local' \
  --exclude 'api/.env.test' \
  --exclude 'api/var/cache/' \
  --exclude 'api/var/log/' \
  --exclude 'api/vendor/' \
  --exclude 'web/node_modules/' \
  --exclude 'mobile/' \
  --exclude 'mobile/.tmp/' \
  --exclude 'api/.phpunit.cache/' \
  -e "$RSYNC_RSH" \
  "${LOCAL_ROOT}/" "${HOST}:${REMOTE_ROOT}/"

if [[ -d "${LOCAL_ROOT}/api/var/books" ]] && [[ -n "$(ls -A "${LOCAL_ROOT}/api/var/books" 2>/dev/null || true)" ]]; then
  echo "==> Rsync books"
  rsync -az --delete \
    -e "$RSYNC_RSH" \
    "${LOCAL_ROOT}/api/var/books/" "${HOST}:${REMOTE_ROOT}/storage/books/"
else
  echo "==> Keep remote books (local api/var/books empty)"
fi

echo "==> Rsync built web dist"
rsync -az --delete \
  -e "$RSYNC_RSH" \
  "${LOCAL_ROOT}/web/dist/" "${HOST}:${REMOTE_ROOT}/web/dist/"

echo "==> Upload dump + env"
if [[ -n "$DUMP_LOCAL" && -f "$DUMP_LOCAL" ]]; then
  scp "${SSH_OPTS[@]}" "$DUMP_LOCAL" "${HOST}:${REMOTE_ROOT}/deploy/data/ssk_book.dump"
fi
if [[ -n "$TMP_ENV" && -f "$TMP_ENV" ]]; then
  scp "${SSH_OPTS[@]}" "$TMP_ENV" "${HOST}:${REMOTE_ROOT}/.env.prod"
  ssh "${SSH_OPTS[@]}" "$HOST" "chmod 600 ${REMOTE_ROOT}/.env.prod"
  rm -f "$TMP_ENV"
fi

if [[ -d "$AIGW_ROOT" ]]; then
  echo "==> Rsync ai-gateway → ${HOST}:${REMOTE_AIGW}"
  rsync -az --delete \
    --exclude '.git/' \
    --exclude '/.env' \
    --exclude 'control-plane/var/' \
    --exclude 'docs/' \
    --exclude '**/tests/' \
    -e "$RSYNC_RSH" \
    "${AIGW_ROOT}/" "${HOST}:${REMOTE_AIGW}/"

  echo "==> Write ai-gateway .env from ssk-book secrets"
  ssh "${SSH_OPTS[@]}" "$HOST" "umask 077; python3 - <<'PY'
from pathlib import Path
vals = {}
for line in Path('${REMOTE_ROOT}/.env.prod').read_text().splitlines():
    if not line.strip() or line.strip().startswith('#') or '=' not in line:
        continue
    k,v = line.split('=',1)
    vals[k.strip()] = v.strip()
out = Path('${REMOTE_AIGW}/.env')
out.write_text(
    f\"AIGW_API_KEY={vals.get('AIGW_API_KEY','dev_only_change_me')}\\n\"
    f\"GEMINI_API_KEY={vals.get('GEMINI_API_KEY','')}\\n\"
    f\"DEEPSEEK_API_KEY={vals.get('DEEPSEEK_API_KEY','')}\\n\"
    f\"DEEPSEEK_GENERATION_MODEL={vals.get('DEEPSEEK_GENERATION_MODEL','deepseek-flash')}\\n\"
    f\"OLLAMA_BASE_URL=http://ollama:11434\\n\"
)
out.chmod(0o600)
print('wrote', out)
PY"

  echo "==> Deploy ai-gateway"
  ssh "${SSH_OPTS[@]}" "$HOST" "bash ${REMOTE_AIGW}/deployments/compose/deploy-prod.sh"
else
  echo "==> Skip ai-gateway rsync (missing ${AIGW_ROOT}); deploy existing remote copy"
  ssh "${SSH_OPTS[@]}" "$HOST" "test -f ${REMOTE_AIGW}/.env && bash ${REMOTE_AIGW}/deployments/compose/deploy-prod.sh"
fi

echo "==> Deploy ssk-book"
ssh "${SSH_OPTS[@]}" "$HOST" "bash ${REMOTE_ROOT}/deploy/deploy-prod.sh"

echo "==> Public smoke"
curl -fsS -o /dev/null -w "public-health:%{http_code}\n" "https://${DOMAIN}/api/health" || true
curl -fsS -o /dev/null -w "public-root:%{http_code}\n" "https://${DOMAIN}/" || true

echo "Done → https://${DOMAIN}"
