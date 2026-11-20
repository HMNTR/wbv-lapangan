FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql

RUN { \
      echo 'date.timezone = Asia/Jakarta'; \
      echo 'post_max_size = 16M'; \
      echo 'upload_max_filesize = 16M'; \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
      echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/app.ini

RUN { \
      echo 'ServerName localhost'; \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
      echo '<Directory /var/www/html>'; \
      echo '    Options -Indexes +FollowSymLinks'; \
      echo '    AllowOverride None'; \
      echo '    Require all granted'; \
      echo '</Directory>'; \
    } > /etc/apache2/conf-available/zz-app-security.conf \
    && a2enconf zz-app-security

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html/
