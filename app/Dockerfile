FROM php:8.3-apache

# PHP: extension MySQL, cấu hình production (tắt hiển thị lỗi, ẩn phiên bản PHP)
# Apache: đổi sang cổng 8080 để chạy được bằng user thường, ẩn phiên bản
RUN docker-php-ext-install pdo pdo_mysql \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername \
 && mkdir -p /var/run/apache2 /var/lock/apache2 \
 && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

COPY --chown=www-data:www-data src/ /var/www/html/

# Hardening: không chạy bằng root
USER www-data
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD php -r "exit(@file_get_contents('http://127.0.0.1:8080/health.php') === false ? 1 : 0);"
