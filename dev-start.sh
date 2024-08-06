#!/usr/bin/env bash

set -e

# get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Verify that the ../OpenConext-devconf/stepup/ssp directory exists relative to this directory
if [ ! -d "$DIR/../OpenConext-devconf/stepup/ssp" ]; then
    echo "The directory $DIR/../OpenConext-devconf/stepup/ssp does not exist. Please clone the OpenConext-devconf repository."
    exit 1
fi

# This script will start the Docker containers, rebuild the images
# The  --force-recreate  flag will recreate the containers even if they are already running.
# The  --build  flag will rebuild the devssp image

echo "Rebuilding the devssp image and starting the containers..."
docker compose up --force-recreate --build -d --wait
echo "Containers are up"

# execute the sync script in the container
echo "Syncing the SSP installation directory to the host"
docker compose exec -it ssp /scripts/sync.sh
echo "Syncing done"

# attach to the containers's log output
docker compose logs -f

