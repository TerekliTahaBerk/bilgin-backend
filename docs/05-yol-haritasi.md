# 05 · Yol Haritası ve Açık Kararlar

---

## 0. Durum (14 Eylül 2026)

| Faz | Durum |
|---|---|
| Faz 0 — İskelet | ✅ Laravel 12, modül iskeleti, Shared katmanı, Sanctum auth, Pest + PHPStan L8 + Pint, mimari testleri, CI |
| Faz 1 — Müfredat + içerik motoru | ✅ Curriculum, Catalog, Identity ve **Admin API** tamam; soru seçim motoru, kilit motoru, yol kurgusu, yayın kapısı ve rol bazlı panel çalışıyor |
| Faz 2 — Oyun döngüsü | ✅ **Tamamlandı**: 10 grader, oturum akışı, can sistemi, XP defteri, level, seri, ilerleme, tekrar kuyruğu ve **sınav provası** (blueprint seçimi + net/puan tahmini) |
| Faz 3 — Para ve büyüme | 🟢 **Billing + sosyal giriş + reklam çalışıyor**: RevenueCat webhook, abonelik, entitlement, premium kapıları, Apple/Google giriş, misafir hesap yükseltme, AdMob SSV + ödüllü can + reklam politikası. Push ve analytics kaldı |
| Faz 4 — Lig ve rozetler | ✅ **Tamamlandı**: lig (tembel katılım, kohort doldurma, Pazartesi 00:00 TRT kapanış, terfi/düşme), 8 rozet (kriter strateji sınıfları, kilitlide ilerleme), profil istatistikleri, zayıf/güçlü konu analizi |
| Faz 5 | ⬜ Başlanmadı |

Kurulu veri: 1 sınav · 3 oturum · 5 alan varyantı · 21 ders · 61 müfredat eşlemesi ·
2 deneme blueprint'i · 6 konu · 2 ünite · 10 node · 68 soru (8 egzersiz tipi).

Çalışan uçlar (25 öğrenci + 18 panel):

| Grup | Uçlar |
|---|---|
| Kimlik | `POST /v1/auth/guest` · **`POST /v1/auth/social`** (Apple/Google + hesap bağlama) · `POST /v1/auth/logout` · `GET /v1/me` · `POST /v1/onboarding` · `PATCH /v1/me/enrollment` |
| İçerik | `GET /v1/me/courses` · `GET /v1/courses/{id}/path` |
| Oyun | `POST /v1/sessions` · `POST /v1/sessions/{id}/answers` · `POST /v1/sessions/{id}/complete` · `POST /v1/sessions/{id}/abandon` |
| Deneme | `GET /v1/exam-simulations` · `POST /v1/exam-simulations` |
| Can | `GET /v1/hearts` |
| Premium | `GET /v1/me/entitlements` · `GET /v1/premium/offerings` · `POST /v1/webhooks/revenuecat` |
| Reklam | `GET /v1/ads/policy` · `GET /v1/webhooks/admob/ssv` |
| Lig | `GET /v1/league/current` · `GET /v1/league/history` |
| Profil | `GET /v1/me/stats` · `GET /v1/me/badges` · `GET /v1/me/topics` |
| Panel | 18 uç — `/api/admin/v1/*` (giriş, ders ağacı, **soru CRUD + şema doğrulama**, şablondan ünite, içe aktarma, kural önizleme, yayınlama, müfredat eşlemesi, **yönetici hesapları**) |

**Geçici bağlama kalmadı.** `ProgressReader`, `ReviewQueueReader` ve
`EntitlementReader` artık gerçek implementasyonlarına bağlı.

**API sözleşmesi**: [`docs/openapi.yaml`](openapi.yaml) — elle yazılır, `ApiContractTest`
her yanıtı ona karşı doğrular ve belgesiz uç kalmasını engeller.

`composer check` (Pint + PHPStan L8 + 319 test) ve `php artisan content:validate` yeşil.
Tam bir ünite (6 node) uçtan uca oynanıp doğrulandı.

---

## 1. Backend fazları

Süreler 1 backend geliştirici varsayımıyla; 2 kişide ~%40 kısalır.

### Faz 0 — İskelet (1–2 hafta)
- Laravel 12 kurulum, Sail (Postgres + Redis), Pint + PHPStan L8 + Pest, CI pipeline
- Modül iskeleti + `Shared` katmanı (Clock, Result, Idempotency, ApiResponse, ExceptionHandler)
- Mimari testleri (Pest Arch): domain → Illuminate yasağı, modüller arası sızıntı yasağı
- Auth: Sanctum, misafir giriş, Apple/Google, hesap bağlama
- **Çıktı**: `POST /auth/guest` + `GET /me` çalışıyor, CI yeşil

### Faz 1 — Müfredat + içerik motoru (3–4 hafta)
- `Curriculum` migration + seeder: **YKS · TYT + AYT + YDT**, 5 varyant (SAY/EA/SÖZ/DİL/
  belirsiz), 21 course, tüm `exam_variant_courses` eşlemesi, TYT ve AYT-SAY blueprint'leri
- `Catalog` migration: subjects, topics (konu ağacı), courses, units, unit_topics,
  unit_nodes, exercises havuzu, unit_templates
- `ExerciseSelector` registry (`Pool`, `Fixed`) + `SelectionRuleValidator` (yayın kapısı)
- 10 egzersiz tipi için JSON şema + `ExerciseContentValidator` registry
- Admin API CRUD + rol/yetki + audit log + draft→review→publish + **şablondan ünite üretme**
- Toplu içe aktarma (CSV/Excel) — içerik ekibi buna bağımlı, erken lazım
- **Çıktı**: Panelden ders/ünite/soru girilip yayınlanabiliyor; `GET /me/courses` TYT ve
  AYT sekmelerini, `GET /courses/{id}/path` ünite yolunu dönüyor

### Faz 2 — Oyun döngüsü (3–4 hafta) ← ürünün kalbi
- `StudySession` akışı: start / answer / complete / abandon, idempotency, snapshot
- 10 `ExerciseGrader` + birim testleri (kısmi puanlama dahil)
- `UnlockRule` motoru + `PathStrategy` (`SequentialPath` → `GradeAwarePath`)
- İlerleme tabloları (node/unit/course/topic)
- `BlueprintSelector` + `ScoringRule` (net/puan tahmini) — sınav provası
- `Hearts` modülü (tembel rejenerasyon, policy'ler)
- `Gamification`: xp_ledger, level eğrisi, streak
- **Çıktı**: Mobil tam bir turu baştan sona oynayabiliyor; sonuç ekranı tek yanıtla besleniyor

### Faz 3 — Para ve büyüme (2–3 hafta)
- RevenueCat webhook + entitlement + `EnsureEntitlement` middleware + paywall verisi
- AdMob SSV + ödüllü can + reklam politikası endpoint'i
- Push (FCM): seri hatırlatma, can doldu, lig sonucu
- Analytics event pipeline
- **Çıktı**: Free/premium ayrımı uçtan uca çalışıyor, ödeme testleri sandbox'ta geçiyor

### Faz 4 — Lig ve rozetler (2 hafta) ✅
- ~~Sıralama, lazy join, haftalık kapanış job'ı (idempotent)~~ ✅
  **Not**: Redis ZSET yerine indeksli tek sorgu kullanıldı. Kohortlar 30
  kişilik olduğu için sıralama milisaniyeler sürüyor; Redis ancak yazma
  çekişmesi gerçekten görülürse gerekir. Şimdiden eklemek kazanılmamış
  karmaşıklık olurdu.
- ~~Rozet kriteri strateji sınıfları~~ ✅ — kriterler JSON, yeni rozet veri işi
- ~~Konu bazlı zayıf/güçlü analizi~~ ✅ — ücretsizde özet, premium'da detay
- **Çıktı**: Lig ekranı ve profil analizi canlı ✅

### Faz 5 — Ölçekleme (sürekli)
- Adaptif soru seçimi, aralıklı tekrar (SM-2)
- Offline içerik paketi (manifest delta + imzalı bundle)
- Yeni sınavlar: LGS → KPSS → ALES → YDS (**kod değişikliği yok, yalnızca içerik**)
- Yük testi, partition'lama, cache ısıtma

**Toplam MVP (Faz 0–3): ~10–13 hafta.** Lig ertelenebilir (Faz 4), müşteri dokümanı da
bunu öneriyor ve haklı.

> Faz 1'in bir hafta uzamasının sebebi TYT+AYT kararı: müfredat eşlemesi, alan varyantları
> ve blueprint'ler baştan kuruluyor. Bu bir hafta, AYT'yi sonradan eklemenin maliyetinden
> (şema göçü + içerik yeniden etiketleme) çok daha ucuz.

---

## 2. Paralel bağımlılıklar

| Ne | Kim | Ne zaman başlamalı |
|---|---|---|
| İçerik üretimi (branş öğretmenleri) | İçerik ekibi | **Faz 1 biter bitmez** — en uzun süren iş |
| Flutter istemcisi | Mobil | Faz 1 sonunda (OpenAPI sözleşmesi hazır olunca) |
| Admin panel (Next.js) | Frontend | Faz 1 ile eşzamanlı |
| App Store / Play hesapları, RevenueCat kurulumu | Ürün | Faz 2'de — onay süreçleri uzun |
| KVKK metinleri, aydınlatma, ebeveyn onayı akışı | Hukuk | Faz 3'ten önce |

> **En büyük risk kod değil içerik.** YKS'nin tamamı (21 course × ~8 ünite × ~6 node,
> havuz payıyla) ≈ **9.000–12.000 soru**. Yapı ilk günden TYT+AYT'yi taşıyor, ama
> *yayın* kademeli: lansmanda 4 TYT dersi + AYT Matematik (~1.400 soru), gerisi 12 hafta
> içinde açılıyor. Yayın bir **veri kararıdır, deploy değil** — ayrıntı: `06` §6.

---

## 3. Tasarımla ilgili bir gözlem

`Mobile app design` klasöründe **iki farklı ürün** var:

1. `Online Dershanem App.dc.html` — canlı ders, koçluk, deneme analizi, Dino AI satan
   pazarlama sitesi (paket kurucu, fiyat kademeleri)
2. `Online Dershanem Oyun v2.dc.html` — bu planın konusu olan oyunlaştırılmış uygulama
   (onboarding 7 adım, ünite yolu, çalışma turu, can, lig, profil)

Bu plan (2)'yi kapsıyor ve ekranlarla birebir örtüşüyor. (1)'deki canlı ders/paket satışı
ileride gelirse `Catalog` ve `Billing` modüllerine **komşu** yeni modüller olarak eklenir
(`LiveClass`, `Packages`) — mevcut şema bunu engellemiyor, ama MVP kapsamında değil.
Eğer iki ürün tek uygulamada birleşecekse bunu şimdi bilmek gerekir; abonelik modeli
(tek premium mi, paket bazlı mı) buna göre değişir.

---

## 4. Kararlar

### Verilenler

| Karar | Sonuç |
|---|---|
| **Kapsam: YKS · TYT + AYT** | Şema ilk günden 21 course, 5 alan varyantı, TYT/AYT sekmesi ve blueprint'leri taşır |
| **Ders sistemi esnek** | Course ↔ varyant *eşleme* ile bağlı; soru havuzu topic'e bağlı; node'lar tarif tutar (`06`) |
| Lansman yayını | 4 TYT dersi + AYT Matematik. Gerisi içerik hazır oldukça açılır, kod değişmez |

### Hâlâ açık

1. **Canlı ders / paket satışı MVP'de var mı?** → *Öneri: yok. Oyun döngüsü tek başına
   ayakta durmalı; canlı ders bambaşka bir operasyon (takvim, öğretmen, video).*
2. **Lig MVP'de mi?** → *Öneri: hayır, Faz 4. Ama XP defteri ilk günden `counts_for_league`
   kolonuyla haftalık toplanabilir yazılsın ki lig açıldığında geçmiş veri olsun.*
3. **Marka adı** — `Tekrarla` mı, `Online Dershanem` mi? Tasarımda ikincisi geçiyor.
4. **Sunucu lokasyonu** — KVKK açısından TR/AB tercih edilir.

---

## 5. Sıradaki adım

Faz 0 ve Faz 1'in büyük kısmı bitti (bkz. §0). Sıradaki üç iş:

1. **Push bildirimleri (FCM)** — seri hatırlatma (kullanıcının seçtiği saatte,
   yalnızca o gün çalışmadıysa), can doldu, lig sonucu. Retention'ın en güçlü
   aracı ve `devices` tablosu zaten push token topluyor.
2. **Günlük görevler** — "Bugün 20 XP kazan", "3 farklı derste çalış".
   Rozet altyapısı (kriter + snapshot) buna doğrudan uyarlanabilir.
3. **İçerik üretimi** — motor tamam, artık darboğaz içerik. Şu an yalnızca
   TYT Tarih + AYT Matematik'te pilot soru var; lansman için 4 TYT dersi
   ve AYT Matematik'in tamamı gerekiyor (~1.400 soru).
Lig, rozetler, push ve analytics sonraki turlarda.
