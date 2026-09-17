# syntax=docker/dockerfile:1

# ── 1) Bağımlılıklar ────────────────────────────────────────────────────
# Ayrı aşama: composer.json değişmedikçe bu katman önbellekten gelir,
# kod değişikliğinde yeniden kurulum yapılmaz.
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./

# --no-dev: Pest, PHPStan ve ide-helper üretime gitmez.
# --no-scripts: artisan henüz kopyalanmadı; script'ler son adımda çalışır.
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ── 2) Çalışma ortamı ───────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
      nginx supervisor \
      postgresql-libs icu-libs oniguruma \
 && apk add --no-cache --virtual .build \
      postgresql-dev icu-dev oniguruma-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl mbstring bcmath opcache \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build

COPY docker/php.ini /usr/local/etc/php/conf.d/tekrarla.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /app
COPY --from=vendor /app /app

# Yazılabilir olması gereken tek iki dizin. Geri kalanı salt okunur
# kalabilir — konteynerde kod değişmemeli.
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

# start-period, provision'ın veritabanını bekleme süresinden (~40sn) uzun
# olmalı; kısa olursa nginx daha ayağa kalkmadan healthcheck başlar ve
# "connection refused" görülür — asıl sorun veritabanıyken yanlış yere bakılır.
# Kurulum başarısız olsa bile nginx başladığı için healthcheck sonunda geçer:
# konteyner ayakta kalır ve hatayı yazan log erişilebilir olur.
HEALTHCHECK --interval=15s --timeout=5s --start-period=75s --retries=5 \
  CMD wget -qO- http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
