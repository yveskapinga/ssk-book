#!/usr/bin/env sh
set -eu

controller_dir="api/src/Controller"

if grep -R -n -E 'Doctrine\\DBAL|Connection|execute(Query|Statement)|fetch(All|One|Associative)|SELECT |INSERT |UPDATE |DELETE ' "$controller_dir"; then
  echo "SQL-first violation: a controller contains SQL or a DBAL dependency."
  exit 1
fi

echo "SQL-first controller boundaries: OK"
