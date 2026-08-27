# Runs a real, generated Laravel app that installs the BPM Engine package
# as an actual Composer dependency — see README.md for the one-time
# bootstrap. This directory is empty until that bootstrap runs.

FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-install pdo_pgsql mbstring zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8001

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8001"]
