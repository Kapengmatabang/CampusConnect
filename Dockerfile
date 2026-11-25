
FROM php:8.2-apache

# Install PHP extensions for MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache URL rewriting
RUN a2enmod rewrite speling
RUN printf '<Directory /var/www/html>\n    CheckSpelling On\n    CheckCaseOnly On\n</Directory>\n' > /etc/apache2/conf-available/speling.conf && a2enconf speling

# Copy CampusConnect files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to use Render's port
CMD ["bash", "-c", "PORT=${PORT:-10000}; sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf; sed -i \"s/:80>/:${PORT}>/\" /etc/apache2/sites-available/000-default.conf; apache2-foreground"]
