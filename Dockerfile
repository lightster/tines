FROM php:5.6-cli-alpine

RUN docker-php-ext-install pcntl

ADD https://getcomposer.org/installer /usr/local/bin/composer-setup.php
RUN php /usr/local/bin/composer-setup.php \
    --quiet \
    --install-dir=/usr/local/bin \
    --filename=composer
