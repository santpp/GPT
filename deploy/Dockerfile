FROM php:8.3-fpm
RUN docker-php-ext-install pdo_mysql
RUN mkdir -p /var/lib/php/sessions && chown www-data:www-data /var/lib/php/sessions && chmod 700 /var/lib/php/sessions
WORKDIR /app
COPY --chmod=755 public/ public/
COPY --chmod=755 src/ src/
COPY --chmod=755 bin/ bin/
COPY --chmod=755 database/ database/
COPY deploy/php-production.ini /usr/local/etc/php/conf.d/zz-agora.ini
COPY --chmod=755 deploy/entrypoint.sh /usr/local/bin/agora-entrypoint
ENTRYPOINT ["agora-entrypoint"]
CMD ["php-fpm"]
