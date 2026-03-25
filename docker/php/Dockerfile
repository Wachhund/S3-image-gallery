ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        libexif-dev \
        libfcgi-bin \
        unzip \
        curl \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        exif \
        pdo_mysql \
        zip \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/healthcheck.conf /usr/local/etc/php-fpm.d/zz-healthcheck.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist 2>/dev/null || true

COPY . .
RUN composer dump-autoload --optimize 2>/dev/null || true

RUN chown -R www-data:www-data /var/www

EXPOSE 9000
