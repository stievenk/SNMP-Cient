FROM php:8.1-cli

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Makassar

# System deps
RUN apt-get update && apt-get install -y \
    snmp \
    libsnmp-dev \
    iputils-ping \
    net-tools \
    tzdata \
    git \
    unzip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Optional MIBs (non-fatal)
RUN apt-get update && apt-get install -y snmp-mibs-downloader \
    || echo "snmp-mibs-downloader skipped"

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Install Composer (official)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# App
WORKDIR /opt/snmp-monitor
COPY . .

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Logs
RUN mkdir -p logs && chmod -R 777 logs

CMD ["php", "monitoring.php"]
