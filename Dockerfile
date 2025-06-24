# Use an official PHP image with Apache web server
FROM php:8.2-apache

# Install System Dependencies for zip and PostgreSQL
# We need `libpq-dev` for the pgsql and pdo_pgsql extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions: zip for backup and pgsql for history
# pdo and pdo_pgsql are essential for database connectivity
RUN docker-php-ext-install pdo pdo_pgsql pgsql
RUN docker-php-ext-install zip

# Set the working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set correct permissions for the web server user
RUN chown -R www-data:www-data /var/www/html
