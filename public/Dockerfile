FROM php:8.2-apache

# Install mysqli (for MySQL connection)
RUN docker-php-ext-install mysqli

# Copy all files to Apache root
COPY . /var/www/html/

# Enable mod_rewrite (for routing if needed)
RUN a2enmod rewrite
