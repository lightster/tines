FROM php:5.6-cli-alpine

RUN docker-php-ext-install pcntl

# git is for composer
# bcmath is for phpunit
# pcntl is for pcntl_* functions
# zip is for composer

RUN apk add --no-cache --virtual '.lightster-phpize-deps' \
        $PHPIZE_DEPS \
    && apk add --no-cache \
        git \
        zlib-dev \
    && docker-php-ext-install \
        bcmath \
        pcntl \
        zip \
    && pecl install xdebug-2.5.5 \
    && docker-php-ext-enable xdebug \
    && apk del --no-cache .lightster-phpize-deps

ADD https://getcomposer.org/installer /usr/local/bin/composer-setup.php
RUN php /usr/local/bin/composer-setup.php \
    --quiet \
    --install-dir=/usr/local/bin \
    --filename=composer
