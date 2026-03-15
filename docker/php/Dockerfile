FROM php:8.3-fpm-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev \
    libssl-dev pkg-config \
    ca-certificates openssl \
 && update-ca-certificates \
 && docker-php-ext-install pdo pdo_mysql \
 && pecl install mongodb \
 && docker-php-ext-enable mongodb \
 && rm -rf /var/lib/apt/lists/*