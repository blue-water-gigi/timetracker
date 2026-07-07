FROM php:8.4.12-fpm-alpine

COPY --from=mlocati/php-extension-installer:2.11.1 /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache \
    bash \
    curl \
    zip \
    unzip \
    ca-certificates

RUN install-php-extensions \
    pdo_pgsql \
    bcmath \
    mbstring \
    intl \
    xdebug \
    gd \
    opcache \
    pcntl \
    sockets

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 9000 5173

CMD ["php-fpm", "-F"]