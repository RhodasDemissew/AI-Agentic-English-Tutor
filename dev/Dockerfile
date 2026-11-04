FROM php:8.2-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev libonig-dev libpq-dev poppler-utils git unzip && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j4 mysqli pgsql intl zip gd soap exif opcache mbstring && rm -rf /var/lib/apt/lists/*
RUN echo 'max_input_vars=5000\nupload_max_filesize=12M\npost_max_size=16M\nmemory_limit=512M' > /usr/local/etc/php/conf.d/moodle.ini
WORKDIR /var/www/html
