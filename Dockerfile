FROM php:8.3-fpm AS builder

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libicu-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql intl zip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./

RUN composer install --no-interaction --no-scripts --no-dev --optimize-autoloader

COPY . .

RUN if [ ! -f /app/.env ]; then \
    printf 'APP_ENV=prod\nAPP_DEBUG=false\nAPP_SECRET=ChangeMeInRailway\n' > /app/.env; \
    fi

RUN composer install --no-interaction --no-dev --optimize-autoloader --no-ansi

# Webpack Encore assets (requires vendor/ for @symfony/ux-turbo file: dependency)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm ci \
    && npm run build \
    && rm -rf node_modules \
    && apt-get purge -y nodejs \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

RUN php bin/console cache:warmup --env=prod --no-debug || true

FROM php:8.3-fpm AS runtime

WORKDIR /app

RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    libicu-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql intl zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /app /app

RUN mkdir -p /app/var /app/config/jwt && \
    chown -R www-data:www-data /app && \
    chmod -R 755 /app && \
    chmod -R 775 /app/var /app/config/jwt

COPY nginx-main.conf /etc/nginx/nginx.conf

RUN rm -rf /etc/nginx/conf.d/* /etc/nginx/sites-enabled /etc/nginx/sites-available
COPY nginx.conf /etc/nginx/conf.d/symfony.conf

COPY entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=3 \
    CMD sh -c 'curl -f "http://127.0.0.1:${PORT:-80}/" || exit 1'

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
