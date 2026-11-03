FROM php:8.2-apache

# Install MySQL database drivers
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy your project files into the container web root
COPY . /var/www/html/

# Set proper file permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
