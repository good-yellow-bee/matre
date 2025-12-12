# Magento 2 Docker Container for ATR
# PHP 8.2 for Magento 2.4.6 compatibility (MFTF binary execution)

FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    libxslt-dev \
    oniguruma-dev \
    mysql-client \
    nodejs \
    npm \
    chromium \
    chromium-chromedriver \
    linux-headers

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        mysqli \
        zip \
        intl \
        xsl \
        soap \
        bcmath \
        sockets \
        opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create necessary directories
RUN mkdir -p /var/www/html/app/code/Test \
    /var/www/html/dev/tests/acceptance/tests/_output

# PHP configuration for Magento
RUN echo "memory_limit=4G" > /usr/local/etc/php/conf.d/magento.ini \
    && echo "max_execution_time=18000" >> /usr/local/etc/php/conf.d/magento.ini \
    && echo "realpath_cache_size=10M" >> /usr/local/etc/php/conf.d/magento.ini \
    && echo "realpath_cache_ttl=7200" >> /usr/local/etc/php/conf.d/magento.ini

# Copy scripts and patches
COPY install.sh /usr/local/bin/install-magento.sh
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY patches/ /usr/local/share/mftf-patches/
RUN chmod +x /usr/local/bin/install-magento.sh /usr/local/bin/entrypoint.sh

# Set environment variables
ENV MAGENTO_RUN_MODE=developer
ENV PATH="/var/www/html/vendor/bin:${PATH}"

# Use entrypoint for auto-installation
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
