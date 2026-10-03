FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM php:8.4-cli
RUN docker-php-ext-install pdo_mysql bcmath
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 8003
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
