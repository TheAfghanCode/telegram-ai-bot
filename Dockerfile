# Use an official PHP image with Apache web server
FROM php:8.2-apache

# --- Layer 1: System Dependencies (This layer will be cached) ---
# Install libraries first, as they rarely change.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Layer 2: PHP Extensions (This layer will also be cached) ---
# Install PHP extensions after system dependencies. This also changes infrequently.
RUN docker-php-ext-install pdo pdo_pgsql pgsql
RUN docker-php-ext-install zip

# --- Layer 3: Application Code (This layer changes often) ---
# Set the working directory
WORKDIR /var/www/html

# Copy project files ONLY AFTER all dependencies are installed.
COPY . .

# --- Layer 4: Permissions (Run after code copy) ---
# Set correct permissions for the web server user
RUN chown -R www-data:www-data /var/www/html
