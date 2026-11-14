#!/usr/bin/env bash

# Run the functional tests (see tests/) in the running devssp container.
# Start the container first with ./dev-start.sh
#
# Usage: ./dev-test.sh [phpunit options]
#   e.g. ./dev-test.sh --filter EntraMock
#        ./dev-test.sh --testdox

set -e

# get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$DIR"

if [[ "$1" == "-h" || "$1" == "--help" ]]; then
    echo "Usage: $0 [phpunit options]"
    echo "Runs the functional tests in tests/ in the running ssp container (start it with ./dev-start.sh)"
    echo "Any options are passed to phpunit, e.g.: $0 --filter EntraMock"
    exit 0
fi

if [[ -z "$(docker compose ps --status running --quiet ssp)" ]]; then
    echo "The ssp container is not running. Start it with ./dev-start.sh"
    exit 1
fi

TESTS_DIR=/var/www/devssp-tests

# Copy the tests to the container. Keep the vendor directory in the container, so composer install is quick.
echo "Copying tests/ to ${TESTS_DIR} in the ssp container"
docker compose exec -T ssp sh -c "mkdir -p ${TESTS_DIR} && cd ${TESTS_DIR} && find . -mindepth 1 -maxdepth 1 ! -name vendor -exec rm -rf {} +"
COPYFILE_DISABLE=1 tar -C tests --exclude=./vendor --exclude=./.phpunit.cache -cf - . \
    | docker compose exec -T ssp tar -C "${TESTS_DIR}" -xf - 2>/dev/null

echo "Installing the test dependencies"
docker compose exec -T -e COMPOSER_ALLOW_SUPERUSER=1 ssp \
    composer --working-dir="${TESTS_DIR}" install --no-interaction --no-progress --quiet

# Use a TTY (colors) when we have one
TTY_OPT=-T
if [[ -t 1 ]]; then
    TTY_OPT=
fi

echo "Running the tests"
# shellcheck disable=SC2086
docker compose exec ${TTY_OPT} -w "${TESTS_DIR}" ssp vendor/bin/phpunit "$@"
