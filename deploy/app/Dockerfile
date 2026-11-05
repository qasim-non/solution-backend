# Combined production image: Nginx + PHP-FPM in one container, PORT-aware.
# Works on Railway, Render, Heroku-style hosts and any Docker host.
FROM php:8.3-fpm

# System dependencies + Nginx
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    zip \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Application code
WORKDIR /var/www/html
COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Laravel storage permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
    && chmod -R 775 storage bootstrap/cache

# Nginx configuration + entrypoint
COPY deploy/app/nginx.conf /etc/nginx/sites-available/laravel
COPY deploy/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

CMD ["/usr/local/bin/entrypoint.sh"]
