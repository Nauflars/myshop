FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libssl-dev \
    librabbitmq-dev \
    zip \
    unzip \
    gosu \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache

# Install MongoDB PHP extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install AMQP PHP extension for RabbitMQ
RUN pecl install amqp && docker-php-ext-enable amqp

# Install PCOV for code coverage reports
RUN pecl install pcov && docker-php-ext-enable pcov

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Configure PHP
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini
RUN echo "upload_max_filesize = 128M" >> /usr/local/etc/php/conf.d/docker-php-uploads.ini
RUN echo "post_max_size = 128M" >> /usr/local/etc/php/conf.d/docker-php-uploads.ini

# Configure OPcache for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-opcache.ini
RUN echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-opcache.ini
RUN echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/docker-php-opcache.ini
RUN echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-opcache.ini

# Copy entrypoint script
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set permissions for initial structure
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php-fpm"]
