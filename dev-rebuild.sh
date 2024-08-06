#!/usr/bin/env bash

set -e

# This script will rebuild / pull Docker the docker images
# then recreate and start the Docker containers

# get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Verify that the ../OpenConext-devconf/stepup/ssp directory exists relative to this directory
if [ ! -d "$DIR/../OpenConext-devconf/stepup/ssp" ]; then
    echo "The directory $DIR/../OpenConext-devconf/stepup/ssp does not exist. Please clone the OpenConext-devconf repository."
    exit 1
fi

docker compose build --no-cache --pull

# Bring the containers up and wait for them to be ready
docker compose up --force-recreate -d --wait

# execute the sync script in the ssp container
echo "Syncing the SSP installation directory to the host"
docker compose exec -it ssp /scripts/sync.sh
echo "Syncing done"

# Attach to the containers's log output
docker compose logs -f