FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-req=ext-ldap

FROM php:8.4-apache-bookworm AS application
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public LDAPTLS_CACERT=/etc/ssl/certs/ca-certificates.crt
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl gosu libicu-dev libldap2-dev libonig-dev libzip-dev libxml2-dev \
    && docker-php-ext-configure ldap --with-libdir="lib/$(dpkg-architecture --query DEB_HOST_MULTIARCH)" \
    && docker-php-ext-install -j"$(nproc)" dom intl ldap mbstring opcache pcntl pdo_mysql zip \
    && a2enmod expires headers rewrite ssl \
    && a2dissite 000-default default-ssl \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=dependencies --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build
COPY docker/apache.conf /etc/apache2/sites-available/is-tools.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/is-tools.ini
COPY docker/entrypoint.sh /usr/local/bin/is-tools-entrypoint
RUN mkdir -p documents storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data documents storage bootstrap/cache \
    && chmod +x /usr/local/bin/is-tools-entrypoint \
    && a2ensite is-tools
EXPOSE 443
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 CMD curl --insecure --fail --silent https://localhost/up > /dev/null || exit 1
ENTRYPOINT ["is-tools-entrypoint"]
CMD ["apache2-foreground"]
