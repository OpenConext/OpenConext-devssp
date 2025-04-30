#!/usr/bin/env bash

set -e

# get the directory of this script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Verify that the ../OpenConext-devconf/stepup/ssp directory exists relative to this directory
if [ ! -d "$DIR/../OpenConext-devconf/stepup/ssp" ]; then
    echo "The directory $DIR/../OpenConext-devconf/stepup/ssp does not exist. Please clone the OpenConext-devconf repository."
    exit 1
fi

# check commandline parameters:
# -h or --help: show help
# -r or --rebuild: force rebuild of the image and containers
FORCE_REBUILD=0
if [[ "$1" == "-h" || "$1" == "--help" ]]; then
    echo "Usage: $0 [-r|--rebuild]"
    echo "  -r, --rebuild   Force rebuild of the image and containers"
    exit 0
elif [[ "$1" == "-r" || "$1" == "--rebuild" ]]; then
    FORCE_REBUILD=1
fi

# Show hint if no parameters are given
if [[ "$#" -eq 0 ]]; then
    echo "Staring the container. Use -h or --help for more options"
    echo ""
fi

if [[ "$FORCE_REBUILD" -eq 1 ]]; then
    echo "Rebuilding the image and containers..."
    # This script will rebuild / pull Docker the docker images
    # then recreate and start the Docker containers
    echo "compose build --no-cache --pull"
    docker compose build --no-cache --pull

    # This script will start the Docker containers, rebuild the images
    # The  --force-recreate  flag will recreate the containers even if they are already running.
    # The  --build  flag will rebuild the devssp image
    echo "Rebuilding the devssp image and starting the containers..."
    docker compose up --force-recreate --build -d --wait
else
    echo "Starting the containers..."
    docker compose up -d --wait
fi

echo "Containers are up"
echo "SSP is now available at https://ssp.dev.openconext.local/"
echo ""

# Use docker compose cp to copy the /var/www directory from the ./container/ directory on the host
echo "Copying the /var/www directory from the container to ./container"
mkdir -p ./container/var/
echo "docker compose cp devssp:/var/www ${DIR}/container/var/www"
docker compose cp ssp:/var/www "${DIR}/container/var/"
echo "Done."
echo ""

# Attach to the container's log output
echo "Following the logs of the containers... Use Ctrl+C to stop."
echo "The containers will continue running in the background."
echo ""
echo "docker compose logs -f"
docker compose logs -f
