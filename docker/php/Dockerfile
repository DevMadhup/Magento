FROM composer:2.8 AS composer

FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    cron \
    wget \
    vim \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libxslt1-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libgmp-dev \
    libldap2-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

RUN docker-php-ext-install -j$(nproc) \
    bcmath \
    ftp \
    sockets \
    gd \
    intl \
    mysqli \
    pdo_mysql \
    soap \
    xsl \
    zip

RUN pecl install redis && \
    docker-php-ext-enable redis

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

RUN groupadd -g 1001 clp && \
    useradd \
    -u 1001 \
    -g clp \
    -m \
    -s /bin/bash \
    test

WORKDIR /var/www/html

USER test

CMD ["php-fpm"]
