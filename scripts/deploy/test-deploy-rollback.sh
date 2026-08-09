#!/usr/bin/env bash
#
# Safety tests for the remote deploy script embedded in .github/workflows/deploy.yml.
#
# Extracts the DEPLOY_SCRIPT heredoc and runs it against a throwaway APP_DIR with
# php/mysqldump/curl/sudo/chown/chgrp/sleep stubbed out, then asserts the on-disk
# result of each failure mode. Nothing here touches a real server or database.
#
# Run:  bash scripts/deploy/test-deploy-rollback.sh
# Exits non-zero if any assertion fails.
#
# Why this exists: the deploy swaps the release directory before warming caches
# and health-checking. Every failure in that window has to reach the rollback
# path, and a retry must never overwrite the last known-good release. Both of
# those regressed silently once already (three failed runs on 2026-07-11), so
# they are pinned here rather than re-discovered in production.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORKFLOW="$REPO_ROOT/.github/workflows/deploy.yml"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0

[ -f "$WORKFLOW" ] || { echo "cannot find $WORKFLOW"; exit 1; }

# ---- extract the remote script from the workflow -------------------------
python3 - "$WORKFLOW" "$WORK/deploy_script.sh" <<'PY'
import sys
src = open(sys.argv[1]).read().splitlines()
try:
    start = next(i for i, l in enumerate(src) if "<< 'DEPLOY_SCRIPT'" in l)
    end   = next(i for i, l in enumerate(src) if l.strip() == 'DEPLOY_SCRIPT' and i > start)
except StopIteration:
    sys.exit("could not locate the DEPLOY_SCRIPT heredoc in the workflow")
# the run: | block scalar is indented 10 spaces; the shell sees it stripped
body = [l[10:] if l.startswith(' ' * 10) else l for l in src[start + 1:end]]
open(sys.argv[2], 'w').write('\n'.join(body) + '\n')
PY

bash -n "$WORK/deploy_script.sh" || { echo "extracted deploy script is not valid bash"; exit 1; }

# ---- stubs ---------------------------------------------------------------
mkdir -p "$WORK/bin"

cat > "$WORK/bin/sudo" <<'EOF'
#!/bin/bash
while [ "$1" = "-u" ]; do shift 2; done
exec "$@"
EOF

cat > "$WORK/bin/php" <<'EOF'
#!/bin/bash
shift                     # drop "artisan"
CMD="$1"
echo "  [php artisan $CMD]"
if [ "$CMD" = "${FAIL_ON:-}" ]; then echo "  !! artisan $CMD failed" >&2; exit 1; fi
# PPID is the deploy shell: the sudo stub exec'd us, so no extra process sits between
if [ "$CMD" = "${KILL_ON:-}" ]; then kill -TERM "$PPID"; fi
exit 0
EOF

cat > "$WORK/bin/mysqldump" <<'EOF'
#!/bin/bash
if [ "${FAIL_ON:-}" = "mysqldump" ]; then echo "  !! mysqldump failed" >&2; exit 1; fi
echo "  [mysqldump argv: $* MYSQL_PWD=${MYSQL_PWD:-unset}]" >&2
echo "-- fake dump"
EOF

cat > "$WORK/bin/curl" <<'EOF'
#!/bin/bash
case "$*" in
  *rollback-check*) echo "${ROLLBACK_CODE:-200}" ;;
  *)                echo "${HEALTH_CODE:-200}" ;;
esac
EOF

printf '#!/bin/bash\nexit 0\n' > "$WORK/bin/chown"
printf '#!/bin/bash\nexit 0\n' > "$WORK/bin/chgrp"
printf '#!/bin/bash\nexit 0\n' > "$WORK/bin/sleep"   # keeps the health-check backoff instant
chmod +x "$WORK"/bin/*

# ---- helpers -------------------------------------------------------------
APP="$WORK/app"

setup() {
  rm -rf "$APP"
  mkdir -p "$APP"/{shared/storage,staging/bootstrap/cache}
  # MAIL_FROM_NAME is deliberately unquoted-with-a-space: sourcing this file
  # instead of parsing it used to abort the deploy with "Admin: command not found"
  printf 'DB_HOST=127.0.0.1\nDB_DATABASE=soli\nDB_USERNAME=soli\nDB_PASSWORD=s3cr#t\nMAIL_FROM_NAME=Soli Admin\n' \
    > "$APP/shared/.env"
  echo NEW > "$APP/staging/RELEASE"
  mkdir -p "$APP/current/bootstrap/cache"; echo GOOD  > "$APP/current/RELEASE"
  mkdir -p "$APP/previous";                echo OLDER > "$APP/previous/RELEASE"
}

run_deploy() {
  sed "s#^APP_DIR=/var/www/admin.soli.nl#APP_DIR=$APP#" "$WORK/deploy_script.sh" > "$WORK/run.sh"
  PATH="$WORK/bin:$PATH" bash "$WORK/run.sh" > "$WORK/out.txt" 2>&1
  echo $? > "$WORK/exit.txt"
}

rel()   { cat "$APP/$1/RELEASE" 2>/dev/null || echo ABSENT; }
state() { cat "$APP/.deploy-state" 2>/dev/null || echo ABSENT; }
code()  { cat "$WORK/exit.txt"; }

check() { # check <label> <actual> <expected>
  if [ "$2" = "$3" ]; then printf '    ok   %s = %s\n' "$1" "$2"; PASS=$((PASS + 1))
  else printf '    FAIL %s = %s (expected %s)\n' "$1" "$2" "$3"; FAIL=$((FAIL + 1)); fi
}

says() { # says <label> <pattern>
  if grep -q "$2" "$WORK/out.txt"; then printf '    ok   %s\n' "$1"; PASS=$((PASS + 1))
  else printf '    FAIL %s\n' "$1"; FAIL=$((FAIL + 1)); fi
}

nonzero() {
  if [ "$(code)" -ne 0 ]; then printf '    ok   exit nonzero (%s)\n' "$(code)"; PASS=$((PASS + 1))
  else printf '    FAIL exited 0\n'; FAIL=$((FAIL + 1)); fi
}

hdr() { printf '\n=== %s\n' "$1"; }

# ---- scenarios -----------------------------------------------------------

hdr "1. happy path"
setup; run_deploy
check exit     "$(code)"          0
check current  "$(rel current)"   NEW
check previous "$(rel previous)"  GOOD
check staging  "$(rel staging)"   ABSENT
check state    "$(state)"         ABSENT

hdr "2. post-swap failure rolls back instead of leaving a broken release live"
setup; FAIL_ON=config:cache run_deploy
check exit    "$(code)"         1
check current "$(rel current)"  GOOD
check failed  "$(rel failed)"   NEW
check state   "$(state)"        ABSENT
says "announced the rollback"        "rolling back"
says "warned migrations are not undone" "migrations are NOT rolled back"

hdr "3. failing health check rolls back, after retrying"
setup; HEALTH_CODE=500 run_deploy
check exit    "$(code)"         1
check current "$(rel current)"  GOOD
check failed  "$(rel failed)"   NEW
check retries "$(grep -c 'Health check attempt' "$WORK/out.txt")" 6

hdr "4. pre-swap failure leaves the live release untouched"
setup; FAIL_ON=mysqldump run_deploy
check exit     "$(code)"          1
check current  "$(rel current)"   GOOD
check previous "$(rel previous)"  OLDER
check staging  "$(rel staging)"   ABSENT
check state    "$(state)"         ABSENT

hdr "5. migration failure aborts before the swap"
setup; FAIL_ON=migrate run_deploy
check exit     "$(code)"          1
check current  "$(rel current)"   GOOD
check previous "$(rel previous)"  OLDER
check staging  "$(rel staging)"   ABSENT

hdr "6. cancelled job / dropped ssh (SIGTERM) still rolls back"
setup; KILL_ON=route:cache run_deploy
check current "$(rel current)"  GOOD
check failed  "$(rel failed)"   NEW
check state   "$(state)"        ABSENT
nonzero   # a signal arrives with $? == 0; a rollback must never report success

hdr "7. retry after a deploy that died post-swap must not destroy the good release"
setup
# simulate an uncaught death after the swap: new release live, state left behind
rm -rf "$APP/previous"; mv "$APP/current" "$APP/previous"; mv "$APP/staging" "$APP/current"
printf 'swapped' > "$APP/.deploy-state"
mkdir -p "$APP/staging/bootstrap/cache"; echo NEWER > "$APP/staging/RELEASE"
run_deploy
check exit     "$(code)"          1
check previous "$(rel previous)"  GOOD    # last known-good survives
check current  "$(rel current)"   NEW
check staging  "$(rel staging)"   NEWER   # nothing was attempted
says "refused to deploy" "Refusing to deploy"

hdr "8. rollback replaces a stale failed/ rather than nesting inside it"
setup; mkdir -p "$APP/failed/junk"; echo STALE > "$APP/failed/RELEASE"
HEALTH_CODE=502 run_deploy
check current "$(rel current)"  GOOD
check failed  "$(rel failed)"   NEW

hdr "9. first-ever deploy with nothing to roll back to says so"
setup; rm -rf "$APP/previous" "$APP/current"
HEALTH_CODE=500 run_deploy
check exit    "$(code)"         1
check current "$(rel current)"  NEW
check state   "$(state)"        swapped   # blocks the next deploy
says "flagged the release as still live" "STILL LIVE"

hdr "10. .env is parsed, not executed"
setup; run_deploy
if grep -q "Admin: command not found\|Admin: not found" "$WORK/out.txt"; then
  printf '    FAIL .env was sourced\n'; FAIL=$((FAIL + 1))
else
  printf '    ok   .env not sourced\n'; PASS=$((PASS + 1))
fi
says "password passed via MYSQL_PWD, not argv" "MYSQL_PWD=s3cr#t"

hdr "11. a rollback onto a release the schema outgrew is reported, not hidden"
setup; HEALTH_CODE=500 ROLLBACK_CODE=500 run_deploy
check current "$(rel current)" GOOD
nonzero
says "reported the site is down"   "ROLLBACK DID NOT RESTORE A WORKING SITE"
says "printed the restore command" "zcat .*pre-deploy-.*sql.gz | mysql"

hdr "12. a successful rollback is verified, not assumed"
setup; HEALTH_CODE=503 ROLLBACK_CODE=200 run_deploy
check current "$(rel current)" GOOD
says "verified the rollback" "Rollback verified"

printf '\n======================================\n'
printf 'PASS=%d FAIL=%d\n' "$PASS" "$FAIL"
[ "$FAIL" -eq 0 ]
