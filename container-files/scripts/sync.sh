#!/usr/bin/env bash

set -e

# Use rsync to sync the SSP installation directory in /var/www/simplesaml to /simplesaml/
# This is done to make the files from the container available on the host machine

# check if rsync is installed, if not use apt to install it
if ! command -v rsync &> /dev/null
then
    echo "rsync could not be found, installing rsync..."
    apt-get update
    apt-get install -y rsync
fi

# rsync from /var/www/simplesaml to /simplesaml, delete any files in /simplesaml that are not in /var/www/simplesaml
rsync -av --delete /var/www/simplesaml/ /simplesaml/