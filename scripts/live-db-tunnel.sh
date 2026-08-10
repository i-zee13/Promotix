#!/usr/bin/env bash
# Live Hostinger MySQL over SSH local-forward.
# App .env should use DB_HOST=127.0.0.1 DB_PORT=3307
#
# SAFE: this script only opens a tunnel. It never runs migrate, seed, or tests.
# Do NOT run: php artisan migrate / migrate:fresh / db:wipe against this DB.

set -euo pipefail

SSH_HOST="${LIVE_SSH_HOST:-145.79.26.215}"
SSH_PORT="${LIVE_SSH_PORT:-65002}"
SSH_USER="${LIVE_SSH_USER:-u124720530}"
LOCAL_PORT="${LIVE_DB_LOCAL_PORT:-3307}"
REMOTE_MYSQL="${LIVE_MYSQL_HOST:-127.0.0.1}:${LIVE_MYSQL_PORT:-3306}"

if nc -z 127.0.0.1 "$LOCAL_PORT" 2>/dev/null; then
  echo "Tunnel already listening on 127.0.0.1:${LOCAL_PORT}"
  exit 0
fi

if [[ -n "${LIVE_SSH_PASSWORD:-}" ]]; then
  if ! command -v expect >/dev/null 2>&1; then
    echo "expect is required when LIVE_SSH_PASSWORD is set" >&2
    exit 1
  fi
  exec expect <<EOF
set timeout 30
spawn ssh -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30 -N -L ${LOCAL_PORT}:${REMOTE_MYSQL} -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}
expect {
  -re {(?i)password:} { send -- "\$env(LIVE_SSH_PASSWORD)\r" }
  timeout { puts stderr "Timeout waiting for SSH password prompt"; exit 1 }
  eof { puts stderr "SSH exited early"; exit 1 }
}
set timeout -1
expect eof
EOF
else
  echo "Opening SSH tunnel (password prompt)..."
  echo "  ssh -N -L ${LOCAL_PORT}:${REMOTE_MYSQL} -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"
  exec ssh -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=30 -N \
    -L "${LOCAL_PORT}:${REMOTE_MYSQL}" -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}"
fi
