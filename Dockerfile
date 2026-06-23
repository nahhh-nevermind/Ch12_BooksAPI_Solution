FROM php:8.2-apache
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo_mysql
RUN a2enmod rewrite
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
