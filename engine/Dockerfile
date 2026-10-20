FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends libssl-dev libzip-dev libonig-dev libxml2-dev unzip git \
    && docker-php-ext-install zip pcntl mbstring dom xml xmlwriter \
    && pecl install mongodb-2.1.2 && docker-php-ext-enable mongodb \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY trade_crawler/ /app/
RUN mkdir -p database resources/views storage/framework/views storage/framework/cache storage/framework/sessions bootstrap/cache
RUN composer install --no-interaction --prefer-dist --optimize-autoloader
ENTRYPOINT ["php", "artisan"]
CMD ["list"]
