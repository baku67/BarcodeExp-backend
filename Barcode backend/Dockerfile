FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl ca-certificates \
    libicu-dev libzip-dev libpq-dev \
  && docker-php-ext-install intl zip pdo_pgsql opcache \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -sS https://get.symfony.com/cli/installer | bash \
 && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony \
 && symfony -V

WORKDIR /app

EXPOSE 8080
