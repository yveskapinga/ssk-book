#!/usr/bin/env sh
set -eu

cd "$(dirname "$0")/.."

controller_dir="api/src/Controller"

if grep -R -n -E 'Doctrine\\DBAL|Connection|execute(Query|Statement)|fetch(All|One|Associative)|SELECT |INSERT |UPDATE |DELETE ' "$controller_dir"; then
  echo "SQL-first violation: a controller contains SQL or a DBAL dependency."
  exit 1
fi

echo "SQL-first controller boundaries: OK"

if find api/src -name '*Service.php' -print0 | xargs -0 grep -n -E 'execute(Query|Statement)|fetch(All|One|Associative)|SELECT |INSERT |UPDATE |DELETE ' ; then
  echo "SQL-first violation: an application service contains SQL."
  exit 1
fi

echo "SQL-first service boundaries: OK"
