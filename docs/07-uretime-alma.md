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

## 2. İlk kurulum (sunucuda, sırayla)

```bash
# APP_KEY zaten .env'de — key:generate'e gerek yok.
php artisan migrate --force          # --seed YOK: üretime pilot içerik gitmez
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

### İlk yönetici hesabı

Seeder üretimde çalışmadığı için panelde hiç hesap yok ve `POST /admins`
zaten oturum açmış bir süper yönetici istiyor. Bu döngü tek komutla kırılır:

```bash
php artisan admin:create --name="Ad Soyad" --email="sen@ornek.com"
# şifre gizli sorulacak (komut geçmişine düşmesin diye)
```

Sonraki hesapları panelden aç.

### İçerik

`migrate --force` boş bir veritabanı bırakır — **21 ders bile yok**. Müfredat
iskeletini yüklemek için:

```bash
php artisan db:seed --class="App\Modules\Curriculum\Database\Seeders\YksExamSeeder" --force
php artisan db:seed --class="App\Modules\Catalog\Database\Seeders\SubjectSeeder" --force
php artisan db:seed --class="App\Modules\Catalog\Database\Seeders\YksCourseSeeder" --force
php artisan db:seed --class="App\Modules\Curriculum\Database\Seeders\YksCurriculumMapSeeder" --force
php artisan db:seed --class="App\Modules\Curriculum\Database\Seeders\YksBlueprintSeeder" --force
php artisan db:seed --class="App\Modules\Catalog\Database\Seeders\UnitTemplateSeeder" --force
php artisan db:seed --class="App\Modules\Gamification\Database\Seeders\BadgeSeeder" --force
```

Bunlar **yapı** verisidir (sınav, ders, şablon, rozet), pilot soru değil.
`PilotContentSeeder`'ı çalıştırma — o test içeriği.

---

## 3. Cron — atlanırsa sessizce bozulur

```
* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1
```

Coolify'da "Scheduled Task" olarak da tanımlanabilir.

Bu kurulmazsa **lig haftaları hiç kapanmaz**: kimse terfi etmez, sıralama
donar ve hata da vermez. Kontrol:

```bash
php artisan schedule:list
# 0 21 * * 0  php artisan league:close   (Pazartesi 00:00 Europe/Istanbul)
```

---

## 4. Kuyruk işçisi

Şu an kuyruğa iş atan bir kod **yok** (rozet ve lig eşzamanlı işleniyor).
İşçi olmadan da çalışır. İleride push bildirimleri eklendiğinde gerekecek:

```bash
php artisan queue:work --tries=3 --max-time=3600
```

---

## 5. Sağlık kontrolü

`GET /up` — Laravel'in yerleşik ucu. Coolify healthcheck olarak bunu kullan.

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
| Cron kaydı | Yoksa lig kapanmaz | **Evet** |
| İlk yönetici hesabı | `admin:create` hazır | **Evet** |
| İçerik | Yalnızca yapı verisi var, soru yok | Öğrenci için evet |
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
