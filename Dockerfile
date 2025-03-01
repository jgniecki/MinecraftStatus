FROM php:7.4-apache

# Install necessary extensions and tools
RUN apt-get update && apt-get install -y \
    zip unzip git curl libpq-dev libonig-dev \
    && docker-php-ext-install mbstring

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

EXPOSE 80