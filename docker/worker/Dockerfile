# Worker container for user embedding queue processing
# Uses same base as main PHP container for consistency
FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    librabbitmq-dev \
    libssl-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
   pdo_mysql \
    intl \
    zip \
    opcache \
    pcntl \
    sockets

# Install PECL extensions
RUN pecl install amqp redis mongodb \
    && docker-php-ext-enable amqp redis mongodb

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for worker processes
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/worker.ini && \
    echo "max_execution_time = 3600" >> /usr/local/etc/php/conf.d/worker.ini && \
    echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/worker.ini && \
    echo "opcache.memory_consumption = 256" >> /usr/local/etc/php/conf.d/worker.ini

# Set working directory
WORKDIR /var/www/html

# Default command (overridden in docker-compose.yml)
CMD ["php", "bin/console", "messenger:consume", "user_embedding_updates", "--time-limit=3600", "--memory-limit=512M"]
