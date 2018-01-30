FROM php:5.6-cli-alpine

RUN docker-php-ext-install pcntl
