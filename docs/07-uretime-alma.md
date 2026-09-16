# 07 · Üretime Alma

Hedef: `https://bilginbackend.cryptoping.io` · Coolify (Docker + Traefik)

---

## 1. `.env` — üretim

```env
APP_NAME=Tekrarla
APP_ENV=production
APP_KEY=base64:mPQ+xDOfzAO7Dh0fgvBKxLn9lFXQmIKLPf/etAPz+vc=
APP_DEBUG=false               # ← true kalırsa hata sayfaları yığın izini ve
                              #    veritabanı bilgilerini ziyaretçiye gösterir
APP_URL=https://bilginbackend.cryptoping.io
APP_TIMEZONE=UTC              # değiştirme — yerel saat hesapları kodda yapılıyor
APP_LOCALE=tr

# --- Veritabanı ---------------------------------------------------------
DB_CONNECTION=pgsql
DB_HOST=dcosgg8k04cowccccco04oks
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<coolify'dan yeni şifre>

# --- Vekil ve CORS ------------------------------------------------------
# Coolify/Traefik arkasında: uygulamaya yalnızca vekil üzerinden erişiliyor.
TRUSTED_PROXIES=*
# Boş veya "*" = her kaynağa açık. Bearer token kullandığımız ve cookie
# göndermediğimiz için bu kurulumda güvenli; korumayı token sağlıyor.
CORS_ALLOWED_ORIGINS=*

# --- Oturum / kuyruk / önbellek ------------------------------------------
# Redis servisi yoksa veritabanı sürücüleri yeterli. Trafik artınca
# CACHE_STORE=redis ve QUEUE_CONNECTION=redis'e geçilebilir (env değişikliği).
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

# --- Log ----------------------------------------------------------------
# Docker'da dosyaya yazmak anlamsız; stderr Coolify log ekranına düşer.
LOG_CHANNEL=stderr
LOG_LEVEL=warning

# --- Sağlayıcı sırları (boşken ilgili özellik KAPALIDIR) ----------------
REVENUECAT_WEBHOOK_SECRET=
APPLE_AUDIENCES=
GOOGLE_AUDIENCES=

# --- Ürün ayarları (varsayılanlar yeterli) ------------------------------
BILLING_PRODUCT_MONTHLY=tekrarla_premium_monthly
BILLING_PRODUCT_YEARLY=tekrarla_premium_yearly
BILLING_TRIAL_DAYS=7
HEARTS_MAX=5
HEARTS_REGEN_MINUTES=18
HEARTS_AD_LIMIT=4
ADS_INTERSTITIAL_EVERY=2
ADS_INTERSTITIAL_MIN_INTERVAL=180
ADS_INTERSTITIAL_GRACE_DAYS=3
LEAGUE_COHORT_SIZE=30
LEAGUE_PROMOTION=5
LEAGUE_DEMOTION=5
```

`ADMIN_SEED_PASSWORD` **yazma** — seeder üretimde zaten çalışmıyor.

### Neden bunlar önemli

**`TRUSTED_PROXIES`** — Bu olmadan `$request->ip()` Traefik'in IP'sini döner.
Sonuç: giriş rate limit'i (5/dk) tüm kullanıcıları tek sayar, yani bir kişi
herkesin giriş hakkını tüketir. Denetim kaydındaki IP'ler de anlamsız olur.
Ayrıca üretilen URL'ler `http://` kalır.

**`CORS_ALLOWED_ORIGINS`** — `*` bu kurulumda güvenli: kimlik doğrulama
Bearer token ile yapılıyor, cookie ile değil. Tarayıcı token'ı kendiliğinden
eklemediği için başka bir sitenin JS'i kullanıcı adına istek atamaz.

Tek istisna: ileride Sanctum'un cookie tabanlı SPA moduna geçilirse
(`supports_credentials: true`), `*` derhal gerçek alan adlarıyla
değiştirilmeli — o zaman tarayıcı oturum cookie'sini otomatik ekler.

**`APP_DEBUG=false`** — `true` kalırsa bir istisna anında veritabanı adresi,
kullanıcı adı ve dosya yolları ziyaretçiye gösterilir.

---

## 2. İlk kurulum — konteyner kendi yapıyor

Coolify'da uygulamayı **Dockerfile** tipiyle oluştur. Konteyner her açılışta
`php artisan app:provision` çalıştırır ve şunları kendisi halleder:

- veritabanını bekler (uygulama DB'den önce ayağa kalkabilir)
- `migrate --force`
- müfredat iskeletini yükler — **yalnızca boşsa**
- `config:cache`, `route:cache`, `event:cache`

İkinci açılışta "Yapı verisi zaten yüklü" der ve geçer. Elle çalıştırılacak
tek şey aşağıdaki yönetici hesabıdır.

Provision'ı atlamak gerekirse: `SKIP_PROVISION=true`.

### İlk yönetici hesabı

Seeder üretimde çalışmadığı için panelde hiç hesap yok ve `POST /admins`
zaten oturum açmış bir süper yönetici istiyor. Bu döngü tek komutla kırılır:

```bash
# Coolify terminalinden, bir kez:
php artisan admin:create --name="Ad Soyad" --email="sen@ornek.com"
# şifre gizli sorulacak (komut geçmişine düşmesin diye)
```

Sonraki hesapları panelden aç. Provision, yönetici yoksa açılış logunda
uyarı basar — atlanması zor.

### İçerik

Provision **yapı** verisini yükler: 1 sınav, 3 oturum, 5 alan varyantı,
21 ders, 61 müfredat eşlemesi, 2 deneme, 2 şablon, 8 rozet.

**Soru yüklemez.** `PilotContentSeeder` bilerek dışarıda — o test içeriği.
Gerçek sorular panelden veya içerik paketi içe aktarmayla girer.

---

## 3. Cron GEREKMİYOR

Zamanlayıcı konteynerin içinde `schedule:work` olarak çalışıyor
(supervisor'da `scheduler` süreci). Harici cron kurmana gerek yok.

Sebebi: cron kurulmayı unutulan bir şeydir ve unutulduğunda lig haftaları
sessizce kapanmaz — hata da vermez. Konteyner ayaktaysa zamanlayıcı da ayakta.

Kontrol:
```bash
php artisan schedule:list
# 0 21 * * 0  php artisan league:close   (Pazartesi 00:00 Europe/Istanbul)
```

---

## 4. Konteyner içinde ne çalışıyor

`supervisord` dört süreç yönetiyor:

| Süreç | İş |
|---|---|
| `nginx` | 8080 portu, belge kökü `public/` |
| `php-fpm` | PHP işleyici |
| `scheduler` | `schedule:work` — lig kapanışı |
| `queue` | `queue:work` — şu an boşta, push bildirimleri için hazır |

Kuyruk işçisi saatte bir yeniden başlar (`--max-time=3600`); uzun ömürlü
PHP süreçleri bellek sızdırır, periyodik yeniden başlatma standart çözümdür.

---

## 5. Sağlık kontrolü

`GET /up` — Dockerfile'da `HEALTHCHECK` olarak da tanımlı, Coolify bunu
otomatik kullanır.

---

## 6. Yayın sonrası doğrulama

```bash
curl -s https://bilginbackend.cryptoping.io/up
# HTTPS zorlanıyor mu, http isteği 301 mi dönüyor?
curl -sI http://bilginbackend.cryptoping.io/up | head -1

# Misafir giriş çalışıyor mu?
curl -s -X POST https://bilginbackend.cryptoping.io/api/v1/auth/guest \
  -H "Accept: application/json" \
  -d device_identifier=smoke-test -d platform=ios

# APP_DEBUG kapalı mı? (olmayan uç 404 dönmeli, yığın izi DEĞİL)
curl -s https://bilginbackend.cryptoping.io/api/v1/olmayan-uc -H "Accept: application/json"
```

Panel girişi:
```bash
curl -s -X POST https://bilginbackend.cryptoping.io/api/admin/v1/auth/login \
  -H "Accept: application/json" \
  -d email=sen@ornek.com -d password=...
```

---

## 7. Yayın öncesi kalanlar

| Konu | Durum | Engel mi? |
|---|---|---|
| DB şifresi rotasyonu | Sohbette paylaşıldı, değiştirilmeli | **Evet** |
| `APP_KEY` | Üretildi, `.env`'e yazılacak | **Evet** |
| Cron kaydı | Konteyner içinde halloldu | Hayır |
| İlk yönetici hesabı | `admin:create` hazır | **Evet** |
| İçerik | Yapı verisi otomatik; **soru yok** | Öğrenci için evet |
| RevenueCat sırrı | Boşsa satın alma çalışmaz | Premium için evet |
| Apple/Google audience | Boşsa sosyal giriş kapalı | Misafir giriş yeterliyse hayır |
| AdMob | Ödüllü reklam çalışmaz | Hayır |
| Sentry | Kurulu değil; hatalar yalnızca log'da | Hayır ama önerilir |
| KVKK metinleri | Yok | **LGS/18 yaş altı için evet** |
| Yedekleme | Coolify'dan ayarlanmalı | **Evet** |

---

## 8. Riskli noktalar

**Veritabanı `postgres` kullanıcısı ve `postgres` veritabanı.** Uygulamaya
süper kullanıcı vermek gereksiz geniş yetki. Coolify varsayılanı böyle ama
ayrı bir `tekrarla` veritabanı ve sınırlı yetkili kullanıcı açmak daha doğru:

```sql
CREATE DATABASE tekrarla;
CREATE USER tekrarla_app WITH PASSWORD '...';
GRANT ALL PRIVILEGES ON DATABASE tekrarla TO tekrarla_app;
```

**Yedekleme.** Migration'lar geri alınabilir ama veri geri gelmez. İlk
kullanıcı girmeden önce otomatik yedek ayarlanmalı.

**`config:cache` sonrası `env()` çalışmaz.** Kodda `env()` yalnızca `config/`
altında kullanılıyor — bu kurala uyuluyor, mimari testi de var. Yeni kod
yazarken bozma.
