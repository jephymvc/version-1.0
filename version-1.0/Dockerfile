FROM php:8.2-apache

# 1. Install system dependencies & clean up in one layer to keep image small
RUN apt-get update && apt-get install -y --no-install-recommends \
	nano \
    libpq-dev \
    default-mysql-client \
    libmariadb-dev \
    libmariadb-dev-compat \
    unixodbc-dev \
    freetds-dev \
    freetds-bin \
    sqlite3 \
    libsqlite3-dev \
    git \
    curl \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libmemcached-dev \
    zlib1g-dev \
    unzip \
    gnupg2 \
    && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions (Removed 'sqlite3' as it's redundant with 'pdo_sqlite')
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    mysqli \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    intl \
    opcache

# 3. Install SQL Server (Microsoft) drivers
# Note: Using msodbcsql18 for better PHP 8.2 compatibility
#	RUN curl -sSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor > /usr/share/keyrings/microsoft-prod.gpg \
#	    && echo "deb [arch=amd64,arm64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/$(. /etc/os-release; echo $VERSION_ID)/prod $(. /etc/os-release; echo $VERSION_CODENAME) main" > /etc/apt/sources.list.d/mssql-release.list \
#	    && apt-get update \
#	    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 \
#	    && pecl install sqlsrv pdo_sqlsrv \
#	    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
#	    && rm -rf /var/lib/apt/lists/*

# 4. Install PECL extensions (Redis, Memcached, MongoDB)
RUN pecl install redis memcached mongodb \
    && docker-php-ext-enable redis memcached mongodb

# 5. Composer & Apache setup
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN a2enmod rewrite headers ssl

# 6. Optimized PHP Configuration
RUN { \
        echo 'upload_max_filesize = 210M'; \
        echo 'post_max_size = 21M'; \
        echo 'max_execution_time = 490'; \
        echo 'memory_limit = 256M'; \
        echo 'date.timezone = UTC'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'error_log = /var/log/php_errors.log'; \
    } > /usr/local/etc/php/conf.d/custom.ini

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=60'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# 7. Copy Application & Set Permissions
# We set ownership DURING the copy to avoid massive layer bloat
COPY --chown=www-data:www-data src/ .

# Ensure subdirectories exist and have correct permissions
RUN mkdir -p public lib vendor storage/logs storage/cache storage/uploads \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# Install PHP dependencies if composer.json exists
RUN if [ -f "composer.json" ]; then \
        composer install --no-dev --no-interaction --optimize-autoloader; \
    fi

# Configure Apache DocumentRoot
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]