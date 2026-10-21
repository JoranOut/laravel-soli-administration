#!/usr/bin/env bash
#
# Fails when a place that names a PHP version disagrees with .php-version.
# With --runtime it also checks the `php` on PATH, so CI cannot test an older
# patch than production is required to run.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

PIN=$(tr -d '[:space:]' < .php-version)
if ! [[ $PIN =~ ^([0-9]+\.[0-9]+)\.[0-9]+$ ]]; then
  echo ".php-version must hold a full X.Y.Z version, got '$PIN'"
  exit 1
fi
MINOR=${BASH_REMATCH[1]}
FAILED=0

fail() { echo "FAIL $*"; FAILED=1; }

# same_minor_as_pin <version> <operator>: true when <version> is on the pinned
# minor and compares to the pin with <operator>
same_minor_as_pin() {
  php -r 'exit(str_starts_with($argv[1], $argv[2] . ".") && version_compare($argv[1], $argv[3], $argv[4]) ? 0 : 1);' \
    "$1" "$MINOR" "$PIN" "$2"
}

grep -Eq "runtimes/$MINOR'?\$" compose.yaml || fail "compose.yaml: Sail build context is not runtimes/$MINOR"
grep -Eq "image: '?sail-$MINOR/app'?\$" compose.yaml || fail "compose.yaml: Sail image is not sail-$MINOR/app"

PLATFORM=$(php -r 'echo json_decode(file_get_contents("composer.json"))->config->platform->php ?? "";')
if [ -z "$PLATFORM" ]; then
  fail "composer.json: config.platform.php is not set"
elif ! same_minor_as_pin "$PLATFORM" "<="; then
  fail "composer.json: config.platform.php is $PLATFORM, must be $MINOR.x and at most $PIN"
fi

if grep -rn "php-version:" .github/workflows; then
  fail ".github/workflows: use php-version-file: .php-version instead of a hard-coded php-version"
fi

if [ "${1:-}" = "--runtime" ]; then
  RUNTIME=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . "." . PHP_RELEASE_VERSION;')
  if ! same_minor_as_pin "$RUNTIME" ">="; then
    fail "runtime PHP is $RUNTIME, must be $MINOR.x and at least $PIN"
  fi
fi

[ "$FAILED" -eq 0 ] && echo "PHP version consistent with .php-version ($PIN)"
exit "$FAILED"
