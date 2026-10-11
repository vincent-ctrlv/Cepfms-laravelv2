# CEPFMS production image: PHP 8.2 + Apache, serving public/ only.
#
#   docker build -t cepfms .
#   docker run -p 8080:80 --env-file .env.docker \
#     -v cepfms-storage:/var/www/html/storage \
#     -v cepfms-uploads:/var/www/html/public/uploads cepfms
#
# Configuration comes from environment variables (no .env file is copied in).
# See "Deploying with Docker" in README.md for the variables and volumes.

FROM php:8.2-apache

# PHP extensions beyond the ones the base image already has
# (curl, fileinfo, mbstring, openssl, pdo, tokenizer, xml are built in).
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: serve public/ (never the project root), allow its .htaccess, and
# turn on the modules it uses.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && a2enmod rewrite headers
COPY docker/apache.conf /etc/apache2/conf-available/cepfms.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/cepfms.ini
RUN a2enconf cepfms

WORKDIR /var/www/html

# Dependencies first, so code changes don't reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
             bootstrap/cache public/uploads/submissions public/uploads/branding \
 && chown -R www-data:www-data storage bootstrap/cache public/uploads

COPY docker/entrypoint.sh /usr/local/bin/cepfms-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/cepfms-entrypoint && chmod +x /usr/local/bin/cepfms-entrypoint

# Safe production defaults; the platform's environment variables override them.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD curl -fsS "http://127.0.0.1:${PORT:-80}/up" || exit 1

ENTRYPOINT ["cepfms-entrypoint"]
CMD ["apache2-foreground"]
