FROM php:8.2-fpm

# Set arguments with default values
ARG USER_ID=1000
ARG GROUP_ID=1000
ARG USER_NAME=laravel

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libssl-dev \
    unzip \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    sockets \
    zip \
    opcache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# Install Node.js (for frontend dependencies)
RUN curl -sL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user
RUN groupadd --gid ${GROUP_ID} ${USER_NAME} \
    && useradd --uid ${USER_ID} --gid ${GROUP_ID} -m ${USER_NAME} \
    && usermod -aG www-data ${USER_NAME} \
    && mkdir -p /home/${USER_NAME}/.composer \
    && chown -R ${USER_NAME}:${USER_NAME} /home/${USER_NAME}

# Configure PHP
COPY docker/php/ /usr/local/etc/php/conf.d/
RUN chown -R ${USER_NAME}:${USER_NAME} /usr/local/etc/php/conf.d

# Set working directory
WORKDIR /var/www

# Set permissions
RUN chown -R ${USER_NAME}:${USER_NAME} /var/www

USER ${USER_NAME}

# Health check
HEALTHCHECK --interval=30s --timeout=3s \
    CMD php -r "exit(fsockopen('localhost', 9000) ? 0 : 1);"