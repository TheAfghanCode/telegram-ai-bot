# Use an official PHP image with Apache web server as a base
# We are using PHP 8.2, which is modern and stable.
FROM php:8.2-apache

# --- Install System Dependencies ---
# We need to install libraries that the PHP extensions depend on.
# `libzip-dev` is required for the zip extension.
# `unzip` is a helpful utility.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Install PHP Extensions ---
# This is where we solve the problem!
# `docker-php-ext-install` is a helper script in the official image to easily install extensions.
RUN docker-php-ext-install zip

# Set the working directory in the container
WORKDIR /var/www/html

# Copy all your project files from your local machine into the container's web root
COPY . .

# --- Set Correct Permissions ---
# This is a crucial step for production.
# It gives the Apache web server user (`www-data`) ownership of all files.
# This ensures that your PHP script can write to the `history` and `archived_history` directories without any permission errors.
RUN chown -R www-data:www-data /var/www/html

# The base image already exposes port 80, so we don't need to do it again.
# The default command starts the Apache server.
