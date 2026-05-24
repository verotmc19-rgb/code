# PHP-ni Apache bilan birga yuklash
FROM php:8.2-apache

# Kerakli kutubxonalarni o'rnatish
RUN apt-get update && apt-get install -y libpng-dev libzip-dev zip \
    && docker-php-ext-install gd zip

# Kodingizni serverga ko'chirish
COPY . /var/www/html/

# Portni ochish
EXPOSE 80
