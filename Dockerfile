FROM php:8.2-apache

# Install mysqli extension for MySQL connectivity
RUN docker-php-ext-install mysqli

# Enable mod_rewrite for URL routing
RUN a2enmod rewrite

# Set the document root to the public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -i 's|/var/www/html|${APACHE_DOCUMENT_ROOT}|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|/var/www/html|${APACHE_DOCUMENT_ROOT}|g' /etc/apache2/apache2.conf

# Allow .htaccess overrides within the public directory
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy application files into the container
COPY . /var/www/html/

EXPOSE 80
