FROM php:8.2-apache
RUN apt-get update && apt-get install -y cron && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo_mysql
RUN a2enmod rewrite
RUN mkdir -p /var/www/html/logs /var/www/html/data /var/www/html/public/storage/generated_images && \
    chmod 777 /var/www/html/logs /var/www/html/data /var/www/html/public/storage/generated_images
WORKDIR /var/www/html
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html && \
    chmod 777 /var/www/html/logs /var/www/html/data /var/www/html/public/storage/generated_images
COPY apache.conf /etc/apache2/sites-available/000-default.conf
VOLUME ["/var/www/html/data", "/var/www/html/public/storage/generated_images", "/var/www/html/logs"]

# Setup cron job
RUN echo '0 2 * * * root cd /var/www/html && /usr/local/bin/php /var/www/html/scripts/cleanup_guests.php >> /var/www/html/logs/cron.log 2>&1' > /etc/cron.d/cleanup-guests && \
    chmod 0644 /etc/cron.d/cleanup-guests

CMD ["sh", "-c", "printenv | grep -E '^(DB_|APP_|DEBUG)' >> /etc/environment && cron && apache2-foreground"]
