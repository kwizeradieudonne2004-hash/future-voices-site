# Future Voices School & Studio — web app image.
# Render has no native PHP runtime, so this ships PHP itself via Docker.
FROM php:8.2-apache

# pdo_mysql: used by config.php. mysqli isn't required by the app but is
# cheap to include for any future admin tooling.
RUN docker-php-ext-install pdo pdo_mysql mysqli

# .htaccess (see .htaccess in the repo) needs mod_rewrite + mod_headers,
# and Apache's default vhost has AllowOverride None — override that so
# .htaccess rules actually apply.
RUN a2enmod rewrite headers \
    && { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/z-app.conf \
    && a2enconf z-app

COPY . /var/www/html/
RUN rm -rf /var/www/html/docker /var/www/html/mysql /var/www/html/Dockerfile /var/www/html/render.yaml \
    && chown -R www-data:www-data /var/www/html

# Render assigns a PORT at runtime (defaults to 10000) and expects the
# service to bind 0.0.0.0:$PORT — Apache's default vhost is hardcoded to
# :80, so entrypoint.sh rewrites it at container start.
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV PORT=10000
EXPOSE 10000
ENTRYPOINT ["/entrypoint.sh"]
