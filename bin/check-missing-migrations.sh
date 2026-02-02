#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# project root
cd "$(dirname "$DIR")"
PROJECT_ROOT="$(pwd)"

php bin/console --env=test migrations:reset
CI_TEST_MIGRATIONS_FILE="$(php bin/console --env=test migrations:create structures 'ci-test')"
CI_TEST_MIGRATIONS_FILE_HOST=$CI_TEST_MIGRATIONS_FILE

if [ ! -f "${CI_TEST_MIGRATIONS_FILE}" ]; then
  # command ran in Docker, but current script is not in Docker
  CI_TEST_MIGRATIONS_FILE_HOST="${CI_TEST_MIGRATIONS_FILE/#\/src/${PROJECT_ROOT}}"
fi

if [ -s "${CI_TEST_MIGRATIONS_FILE_HOST}" ]; then
  printf "Some entity changes are not covered by migrations: \n"
  cat "${CI_TEST_MIGRATIONS_FILE_HOST}"
  rm "${CI_TEST_MIGRATIONS_FILE_HOST}"
  exit 1
fi

rm "${CI_TEST_MIGRATIONS_FILE_HOST}"
