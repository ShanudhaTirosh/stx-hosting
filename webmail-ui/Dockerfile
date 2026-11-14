# Application image (PHP-FPM). nginx runs in its own container, see docker-compose.yml.
# NOTE: written for PHP 8.3 official images; not built in the environment this was developed in.
FROM php:8.3-fpm-alpine

COPY deploy/php-webmail.ini /usr/local/etc/php/conf.d/99-webmail.ini
COPY deploy/php-fpm-pool.docker.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /srv/webmail
COPY --chown=root:www-data bin ./bin
COPY --chown=root:www-data src ./src
COPY --chown=root:www-data config ./config
COPY --chown=root:www-data views ./views
COPY --chown=root:www-data public ./public

# Sessions and rate-limit files live on a volume, outside the web root.
RUN mkdir -p /var/lib/webmail/sessions /var/lib/webmail/ratelimit && chown -R www-data:www-data /var/lib/webmail
ENV STORAGE_PATH=/var/lib/webmail

USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]
