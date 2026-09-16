#!/bin/sh
set -e

# Konteyner her açılışta kendini hazırlar: migration, yapı verisi, önbellek.
# Elle çalıştırılacak adım bırakmamak bilinçli — unutulan bir migrate,
# uygulamayı her istekte 500'e düşürür ve sebebi log'a bakmadan anlaşılmaz.
#
# İdempotent: ikinci açılışta migration'lar zaten uygulanmış, seed yalnızca
# müfredat boşsa çalışır.
if [ "${SKIP_PROVISION:-false}" != "true" ]; then
    php /app/artisan app:provision --no-interaction
fi

exec "$@"
