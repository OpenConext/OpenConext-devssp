FROM ghcr.io/openconext/openconext-basecontainers/php82-apache2-node20-composer2:latest
# See:
# https://github.com/OpenConext/OpenConext-BaseContainers/blob/main/php82-apache2-node20-composer2/Dockerfile

# https://github.com/OpenConext/OpenConext-BaseContainers/blob/main/php82-apache2/Dockerfile
# Exposes PORT 80 for apache

LABEL maintainer="pieter.vandermeulen@surf.nl"
WORKDIR /var/www/simplesaml
RUN curl -L "https://github.com/simplesamlphp/simplesamlphp/releases/download/v2.1.2/simplesamlphp-2.1.2.tar.gz" | tar -xvz -C /var/www/simplesaml --strip-components=1
COPY ./modules/ /var/www/simplesaml/my-modules/
COPY ./html/* /var/www/html
COPY ./sspwww/* /var/www/simplesaml/public/ 
COPY ./sspconf/ /var/www/simplesaml/config/
COPY ./sspattributemap/Openconext_short_to_urn.php /var/www/simplesaml/attributemap/
COPY ./conf/000-default.conf /etc/apache2/sites-enabled/

# Install the debugsp module from local source code.
# First add the local directory with the module's source code to the composer repositories.
RUN composer config repositories.debugsp path /var/www/simplesaml/my-modules/debugsp

# Use composer to install the module so that the simplesamlphp/composer-module-installer can do its thing.
RUN COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_MIRROR_PATH_REPOS=1 composer require pmeulen/simplesamlphp-module-debugsp:dev-main
