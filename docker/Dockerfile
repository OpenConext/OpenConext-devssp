FROM ghcr.io/openconext/openconext-basecontainers/php80-apache2:latest 
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
LABEL maintainer="bart.geesink@surf.nl"
WORKDIR /var/www/simplesaml
RUN curl -L "https://github.com/simplesamlphp/simplesamlphp/releases/download/v2.0.4/simplesamlphp-2.0.4.tar.gz" | tar -xvz -C /var/www/simplesaml --strip-components=1 && \
  /usr/bin/composer require simplesamlphp/simplesamlphp-module-saml2debug --no-update
COPY ./sspwww/* /var/www/simplesaml/public/ 
COPY ./sspconf/ /var/www/simplesaml/config/
COPY ./sspattributemap/Openconext_short_to_urn.php /var/www/simplesaml/attributemap/
COPY ./conf/000-default.conf /etc/apache2/sites-enabled/
