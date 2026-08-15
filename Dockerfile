FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    unzip git libzip-dev \
    && docker-php-ext-install zip \
    && a2enmod rewrite headers \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-interaction

COPY . .

RUN mkdir -p storage/logs \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80