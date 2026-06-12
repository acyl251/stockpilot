FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    build-base \
    libnsl \
    libaio \
    libstdc++ \
    supervisor \
    curl \
    zip \
    unzip \
    git

# Install PHP extensions
RUN docker-php-ext-install pdo pcntl bcmath

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Install OCI8 for Oracle (requires Oracle Instant Client)
# Download Instant Client from Oracle and place in docker/php/instantclient/
# COPY instantclient/ /opt/oracle/instantclient/
# RUN echo /opt/oracle/instantclient > /etc/ld.so.conf.d/oracle.conf && ldconfig
# RUN pecl install oci8 && docker-php-ext-enable oci8
# RUN docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/opt/oracle/instantclient
# RUN docker-php-ext-install pdo_oci

WORKDIR /var/www/html

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 9000
CMD ["php-fpm"]
