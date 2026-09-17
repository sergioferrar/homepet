#!/usr/bin/env bash
# =====================================================
# Aplica os índices de performance em todos os bancos homepet_*
# Uso:
#   HOST=2.25.148.110 USER=homepet_user PASS='SENHA' \
#     bash migrations/aplicar_indices_todos_tenants.sh
# =====================================================
set -euo pipefail

HOST="${HOST:?defina HOST}"
USER="${USER:?defina USER}"
PASS="${PASS:?defina PASS}"
PORT="${PORT:-3306}"

BANCOS=$(mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" -N -e \
  "SHOW DATABASES LIKE 'homepet\\_%'")

for db in $BANCOS; do
  echo "▶ Aplicando índices em $db"
  if [ "$db" = "homepet_login" ]; then
    mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" "$db" \
      < migrations/indices_performance_login.sql
  else
    mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" "$db" \
      < migrations/indices_performance.sql
  fi
done

echo "✔ Concluído."
