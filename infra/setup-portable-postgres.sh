#!/usr/bin/env bash
# One-shot portable PostgreSQL setup for job-pilot (no admin rights needed).
set -euo pipefail

ZIP="/c/Users/ThinkPad/Downloads/pgsql16.zip"
ROOT="/c/Users/ThinkPad/pgsql"
DATA="$ROOT/data"
LOG="$ROOT/pg.log"
PWFILE="$ROOT/pwfile"

if [ ! -f "$ZIP" ]; then echo "zip missing"; exit 1; fi

if [ ! -d "$ROOT/pgsql/bin" ] && [ ! -d "$ROOT/bin" ]; then
  echo "Unzipping..."
  mkdir -p "$ROOT"
  unzip -q "$ZIP" -d "$ROOT"
fi

# EDB zips extract to pgsql/ inside the target dir
if [ -d "$ROOT/pgsql/bin" ]; then BIN="$ROOT/pgsql/bin"; else BIN="$ROOT/bin"; fi
echo "BIN=$BIN"

echo "change-me" > "$PWFILE"

if [ ! -f "$DATA/PG_VERSION" ]; then
  echo "initdb..."
  "$BIN/initdb.exe" -D "$(cygpath -w "$DATA")" -U jobpilot --auth=scram-sha-256 --pwfile="$(cygpath -w "$PWFILE")" -E UTF8 --locale=C
fi

if ! "$BIN/pg_isready.exe" -h 127.0.0.1 -p 5432 -q 2>/dev/null; then
  echo "Starting server..."
  # pg_ctl does not return under Git Bash: it inherits the pty through the
  # postgres child. Background it and poll pg_isready instead.
  "$BIN/pg_ctl.exe" -D "$(cygpath -w "$DATA")" -l "$(cygpath -w "$LOG")" \
    -o "-p 5432 -c listen_addresses=127.0.0.1" start >/dev/null 2>&1 &
fi

for i in $(seq 1 30); do "$BIN/pg_isready.exe" -h 127.0.0.1 -p 5432 -q && break; sleep 1; done
"$BIN/pg_isready.exe" -h 127.0.0.1 -p 5432 || { echo "server failed to start"; tail -20 "$LOG"; exit 1; }

export PGPASSWORD=change-me
"$BIN/psql.exe" -h 127.0.0.1 -U jobpilot -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='jobpilot'" | grep -q 1 || \
  "$BIN/createdb.exe" -h 127.0.0.1 -U jobpilot jobpilot
"$BIN/psql.exe" -h 127.0.0.1 -U jobpilot -d postgres -tc "SELECT 1 FROM pg_database WHERE datname='jobpilot_test'" | grep -q 1 || \
  "$BIN/createdb.exe" -h 127.0.0.1 -U jobpilot jobpilot_test

echo "PostgreSQL ready: jobpilot + jobpilot_test on 127.0.0.1:5432 (user jobpilot)"
