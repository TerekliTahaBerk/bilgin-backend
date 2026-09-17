#!/bin/sh
set -e

# Konteyner her açılışta kendini hazırlar: migration, yapı verisi, önbellek.
# Elle çalıştırılacak adım bırakmamak bilinçli — unutulan bir migrate,
# uygulamayı her istekte 500'e düşürür ve sebebi log'a bakmadan anlaşılmaz.
#
# İdempotent: ikinci açılışta migration'lar zaten uygulanmış, seed yalnızca
# müfredat boşsa çalışır.
if [ "${SKIP_PROVISION:-false}" != "true" ]; then
    # Kurulum başarısız olsa BİLE web sunucusu başlar.
    #
    # Aksi hâlde veritabanı sorununda konteyner açılmadan ölüyor: healthcheck
    # "connection refused" diyor, dağıtım geri alınıyor ve sebebi yazan log
    # satırına erişilemiyor. Yani sorunu gösteren tek yer, sorun yüzünden
    # kayboluyordu.
    #
    # Bu hâlde /up yanıt verir, konteyner ayakta kalır, log okunabilir.
    # Veritabanına bağlı uçlar 500 döner — bu DOĞRU davranış: hata
    # gizlenmiyor, yalnızca teşhis edilebilir hâle geliyor.
    if ! php /app/artisan app:provision --no-interaction; then
        echo
        echo '================================================================'
        echo ' UYGULAMA KISITLI MODDA BAŞLIYOR'
        echo
        echo ' Kurulum tamamlanamadı — sebep yukarıdaki "Sebep" satırında.'
        echo ' /up yanıt verir, veritabanına bağlı uçlar 500 döner.'
        echo ' Sorun giderildikten sonra konteyneri yeniden başlat.'
        echo '================================================================'
        echo
    fi
fi

exec "$@"
