# tekrarla_backend

Laravel 12 · PHP 8.4 · PostgreSQL 16 · Redis · Pest · PHPStan L8

Sınav hazırlık uygulamasının API'si ve içerik motoru. Mimari kararlar ve gerekçeleri
[`docs/`](docs/) altında — özellikle [`docs/06-ders-ve-mufredat-sistemi.md`](docs/06-ders-ve-mufredat-sistemi.md)
okunmadan içerik şemasına dokunulmamalı.

## Kurulum

```bash
composer install
cp .env.example .env && php artisan key:generate
createdb tekrarla && createdb tekrarla_test
php artisan migrate --seed
php artisan content:validate      # yayın kapısı — havuz yeterli mi?
```

Yerel panel hesapları (yalnızca `local`/`testing`):
`admin@tekrarla.test` · `editor@tekrarla.test` · `denetci@tekrarla.test`
— şifre `ADMIN_SEED_PASSWORD` (varsayılan `tekrarla-local`).

## Komutlar

| Komut | Ne yapar |
|---|---|
| `composer check` | Pint + PHPStan L8 + tüm testler (CI'ın yaptığı) |
| `composer fresh` | Veritabanını sıfırlayıp yeniden seed'ler ve içeriği doğrular |
| `composer test:unit` | Yalnızca domain testleri — **veritabanı gerekmez**, saniyeler sürer |
| `composer test:arch` | Modül sınırı ve bağımlılık yönü testleri |
| `composer test:contract` | API yanıtlarını `docs/openapi.yaml` ile karşılaştırır |
| `php artisan content:validate` | Yayındaki her node'un soru havuzunu kuru çalıştırır |

## Şu an ne var

**Identity** — misafir giriş (kayıt ekranı yok), **Apple/Google giriş**
(JWT imza + audience + süre doğrulaması), misafir hesabı kalıcıya çevirme,
Sanctum token, onboarding (`exam_code` + `field` → tek varyant), alan
değiştirme, cihaz kaydı.

**Curriculum** — YKS'nin tam yapısı: 3 oturum (TYT/AYT/YDT), 5 alan varyantı, 21 dersin
varyantlara eşlenmesi, 2 deneme blueprint'i, ders listesi okuma modeli.

**Catalog** — konu ağacı, ders müfredatı, ünite, node tarifi, soru havuzu, ünite
şablonları, içerik içe aktarma, soru seçim motoru (`Pool`/`Fixed`/`ReviewQueue`),
kilit motoru (`UnlockRule`), yol kurgusu (`PathStrategy`), yayın kapısı.

**Learning** — oturum akışı (başlat/cevapla/tamamla/bırak), **10 egzersiz
değerlendiricisi**, ilerleme tabloları (node/ünite/ders/konu), tekrar kuyruğu,
hile işaretleme ve **sınav provası** (blueprint'ten soru toplama + net/puan
tahmini; can harcamaz, üniteye bağlı değildir).

**Hearts** — tembel rejenerasyon (cron yok), free/premium politikaları,
idempotent harcama, denetim izi.

**Gamification** — XP defteri (append-only, çift XP imkânsız), level eğrisi,
saat dilimine duyarlı seri takibi, **8 rozet** (kriterler JSON; kilitlide
ilerleme gösterir), profil istatistikleri.

**Çalışan API (12 uç)**

| Uç | Ekran |
|---|---|
| `POST /api/v1/auth/guest` | Karşılama · "Başla" |
| `POST /api/v1/auth/social` | "Hesabım var" · Apple/Google |
| `POST /api/v1/onboarding` | Onboarding adım 1–7 |
| `GET /api/v1/me` | Profil başlığı |
| `PATCH /api/v1/me/enrollment` | Alan değiştirme |
| `GET /api/v1/me/courses` | Öğren · ders seçimi (TYT/AYT sekmeleri) |
| `GET /api/v1/courses/{id}/path` | Ünite yolu (imza ekran) |
| `POST /api/v1/sessions` | Çalışma ekranı açılışı |
| `POST /api/v1/sessions/{id}/answers` | Her soru için cevap |
| `POST /api/v1/sessions/{id}/complete` | **Sonuç ekranının tamamı, tek yanıt** |
| `POST /api/v1/sessions/{id}/abandon` | Turu yarıda bırakma |
| `GET /api/v1/exam-simulations` | Deneme listesi |
| `POST /api/v1/exam-simulations` | **Sınav Provası** ekranı |
| `GET /api/v1/hearts` | Can göstergesi / "Canların bitti" modalı |
| `GET /api/v1/me/entitlements` | Premium durumu / özellik kapıları |
| `GET /api/v1/premium/offerings` | Paywall ekranı |
| `POST /api/v1/webhooks/revenuecat` | Sağlayıcıdan (istemci çağırmaz) |
| `GET /api/v1/ads/policy` | Hangi reklam gösterilecek |
| `GET /api/v1/webhooks/admob/ssv` | AdMob'dan (istemci çağırmaz) |
| `GET /api/v1/league/current` | **Lig** sekmesi |
| `GET /api/v1/league/history` | Profil · Lig Geçmişim |
| `GET /api/v1/me/stats` | **Profil** üst bloğu |
| `GET /api/v1/me/badges` | Profil · Başarılarım |
| `GET /api/v1/me/topics` | Profil · konu analizi (premium'da detay) |
| `POST /api/v1/auth/logout` | Ayarlar |

**Billing** — RevenueCat webhook (imza doğrulamalı, idempotent), abonelik
durumları (grace'te erişim sürer, refund anında keser), entitlement önbelleği,
premium kapıları.

**League** — haftalık yarış: tembel katılım (ilk XP'de), kohort doldurma,
Pazartesi 00:00 TRT kapanış job'ı (idempotent), terfi/düşme kuralları,
lig geçmişi. Gamification'a bağlı değil — `XpAwarded` olayını dinliyor.

**Ads** — AdMob sunucu tarafı doğrulama (ECDSA imza, ham sorgu dizesi üzerinde),
ödüllü reklamla can kazanma, günlük limit (yerel güne göre), reklam politikası
(ne gösterileceğine sunucu karar verir).

**Admin** — ayrı guard ve ayrı tablo; rol bazlı yetki (editör yazar, denetçi
yayınlar — dört göz ilkesi), **soru düzenleme** (10 tip için şema doğrulaması,
sürümleme, arşivleme), **yönetici hesap yönetimi** (kilitlenme önlemeli),
denetim kaydı, yayın kapısı, müfredat eşlemesi. 18 uç: `/api/admin/v1/*`

**API sözleşmesi** — [`docs/openapi.yaml`](docs/openapi.yaml). Elle yazılır;
`ApiContractTest` her yanıtı ona karşı doğrular ve belgesiz uç kalmasını
engeller. Flutter ve Next.js ekipleri buna göre paralel çalışabilir.

**Pilot içerik** — TYT Tarih "İlk ve Orta Çağlarda Türk Dünyası" (6 node, 44 soru) ve
AYT Matematik "Türev" (4 node, 24 soru). 8 egzersiz tipi kullanımda.

> Pilot sorular motoru doğrulamak için yazılmıştır; yayına çıkmadan önce branş
> öğretmeni incelemesi gerekir. `database/content/*.json` — kod değil veri.

## Şu an ne yok

Günlük görevler, push bildirimleri ve analytics bekliyor.

> Motor tamamlandı; darboğaz artık **içerik**. Şu an yalnızca TYT Tarih ve
> AYT Matematik'te pilot soru var.

**Geçici bağlama kalmadı** — `ProgressReader`, `ReviewQueueReader` ve
`EntitlementReader` gerçek implementasyonlarına bağlı.

## Mimarinin üç kuralı

1. **Domain katmanı Laravel tanımaz.** `app/Modules/*/Domain` içinde `Illuminate\`
   import edilemez — mimari testi bunu zorlar. Getirisi: seçim/kilit/XP mantığı
   veritabanısız, milisaniyelerde test edilir.
2. **Modüller birbirinin tablosuna dokunmaz.** İletişim yayımlanmış arayüzler ve
   domain event'leri üzerinden. İzin verilen tek geçiş: `Curriculum → Catalog`
   (course referansı). Catalog ve Curriculum, Identity'nin `User` modelini tanımaz —
   kullanıcı bilgisi `Shared\Domain\Learner\LearnerProfileReader` üzerinden okunur.
   Hepsi mimari testiyle zorlanır.
3. **Cevap anahtarı istemciye gitmez.** `Exercise::$hidden` son savunma hattıdır;
   asıl koruma API Resource'larının `answer_key`'i hiç okumamasıdır.

## Dizin haritası

```
app/
├── Modules/
│   ├── Identity/            kim: kullanıcı, auth, onboarding, kayıt
│   │   ├── Domain/Enum/     Grade, DailyGoal
│   │   ├── Application/     RegisterGuestUser, CompleteOnboarding, ChangeExamField
│   │   ├── Infrastructure/  modeller + EloquentLearnerProfileReader
│   │   └── Http/            controller, request, resource, route
│   ├── Curriculum/          sınav yapısı: öğrencinin neye ihtiyacı var
│   │   ├── Domain/          FieldCode, VariantCourseReader, VariantCourseRow
│   │   ├── Application/     GetLearnerCourses (+ view DTO'ları)
│   │   ├── Infrastructure/  modeller, okuma modeli, provider
│   │   └── Database/        migration + seeder (YKS ağacı)
│   └── Catalog/             içerik: elimizde ne var
│       ├── Domain/
│       │   ├── Enum/        CourseScope, NodeType, DifficultyLevel,
│       │   │                ExerciseType, SelectionMode
│       │   ├── Contract/    ExercisePool, UnitTopicReader, ReviewQueueReader,
│       │   │                ProgressReader
│       │   ├── Selection/   SelectionRule, SelectorRegistry, Strategy/*
│       │   ├── Validation/  ValidatorRegistry, Validator/* (10 tip)
│       │   ├── Unlock/      UnlockRuleFactory, Rule/* (composite kural ağacı)
│       │   └── Path/        PathStrategy, SequentialPath, GradeAwarePath
│       ├── Application/UseCase/   CreateUnitFromTemplate, ImportContentPackage,
│       │                          ValidateSelectionRule, GetCoursePath
│       ├── Infrastructure/  Eloquent modelleri, repository'ler, provider
│       ├── Console/         content:validate
│       └── Database/        migration + seeder
└── Shared/
    ├── Clock/               ClockInterface, SystemClock, FrozenClock
    ├── Http/                ApiController, ApiResponse, ModuleRoutes
    └── Domain/
        ├── Enum/            PublishStatus, AccessLevel
        ├── Entitlement/     EntitlementReader, Entitlements
        └── Learner/         LearnerProfileReader, LearnerProfile

database/content/            içerik paketleri (JSON) — soru havuzu burada
docs/                        mimari kararlar
tests/
├── Unit/                    domain testleri, DB yok
├── Feature/                 gerçek Postgres'e karşı
└── Architecture/            modül sınırları
```
