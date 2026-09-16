# 04 · İş Kuralları

Tüm sayısal değerler `config/tekrarla/*.php` içinde — kod değişmeden ayarlanabilir,
A/B testine açık. Aşağıdaki değerler başlangıç önerisidir.

---

## 1. Can (Hearts)

```php
// config/tekrarla/hearts.php
'max' => 5,
'regen_interval_minutes' => 18,      // 5 can ≈ 90 dk
'ad_reward_daily_limit' => 4,
'practice_refill_enabled' => true,   // "Pratik yaparak 1 can kazan"
```

**Kurallar**
- Yanlış cevap → −1 can. Doğru cevap can vermez.
- `node_type = exam_sim` ve `placement` (seviye tespiti) can harcamaz — gerçek sınav
  havası bozulmasın, onboarding'de kullanıcı kaybedilmesin.
- Can 0 iken yeni oturum başlatılamaz (`409 HEARTS_DEPLETED`); **devam eden oturum**
  son canla biter, sonuç ekranı normal gösterilir, ardından "Canların bitti" akışı.
- Rejenerasyon **tembel hesaplanır** (okuma anında), cron yok:
  `hearts = min(5, hearts + floor((now − last_regen_at) / 18dk))`, ardından
  `last_regen_at += kazanılan * 18dk` (kalan süre korunur — kullanıcı adaletsizlik hissetmez).
- Premium: `UnlimitedHeartPolicy` — `consume()` no-op, UI `∞` gösterir.

**Kazanma yolları**: bekleme · ödüllü reklam (günde max 4, SSV doğrulamalı) ·
can harcamayan "pratik turu" tamamlama (1 can) · premium.

> Denge notu: reklamla can sınırsız olursa premium'un değeri sıfırlanır. Günlük 4 limiti
> ve pratik turunun 1 can vermesi bilinçli — dönüşüm hunisi bunun üzerine kurulu.

---

## 2. XP ve Level

```php
// config/tekrarla/xp.php
'base_by_difficulty' => [
    'kolay' => 10, 'kolay_orta' => 15, 'orta' => 20,
    'orta_zor' => 30, 'zor' => 40, 'sinav_provasi' => 60,
],
'node_type_multiplier' => [
    'study' => 1.0, 'quick_review' => 0.5,
    'mini_challenge' => 2.0, 'unit_challenge' => 3.0, 'exam_sim' => 3.0,
],
'bonuses' => [
    'perfect' => 20,              // hatasız tamamlama
    'first_completion' => 60,     // bir node'un ilk kez bitirilmesi
    'daily_goal_reached' => 15,
    'streak_milestone' => [7 => 50, 30 => 200, 100 => 500],
],
```

**Hesap zinciri** (dekoratör — yeni bonus eklemek mevcut sınıfa dokunmaz):
`BaseDifficultyXp → NodeTypeMultiplier → PerfectBonus → FirstCompletionBonus → DailyGoalBonus`

**Tekrar oynama**: bir node ikinci kez oynandığında `first_completion` verilmez ve taban XP
%50'ye düşer (`replay_factor => 0.5`) — grind ile lig manipülasyonu engellenir.

**Level eğrisi**: `level = floor(sqrt(total_xp / 25)) + 1` yerine tablo tabanlı
(`LevelCurve` sınıfı, `config` dizisi): Lv1 0, Lv2 100, Lv3 250, Lv4 450, Lv5 700,
Lv6 1.000, Lv7 1.350… (artan fark). Tablo bitince `+500/level` doğrusal devam.

**İki ayrı level var** (tasarımda ikisi de görünüyor):
- Profil level'ı → `user_stats.total_xp`
- Ders level'ı ("TYT Tarih Lv 6") → `user_course_progress.xp`, aynı eğri
  (TYT Tarih ve AYT Tarih-1 **ayrı** level'lardır — ayrı müfredat, ayrı ilerleme;
  ama altlarındaki konu ustalığı `user_topic_stats`'ta ortaktır)

XP her zaman `xp_ledger`'a yazılır; `user_stats.total_xp` türev. `unique(source_type, source_id)`
sayesinde aynı oturum iki kez XP üretemez.

---

## 3. Seri (Streak)

- Bir gün "sayılır" = kullanıcının yerel gününde **en az 1 çalışma tamamlandı**
  (günlük hedef değil, 1 tur yeter — tasarımdaki "Seriyi korumak için bir tur yeter").
- Gün sınırı kullanıcının `timezone`'una göre; sunucu UTC saklar.
- `last_study_date` dünse → `current_streak++`. Bugünse → değişmez. Daha eskiyse →
  streak freeze var mı bakılır; yoksa `current_streak = 1`.
- **Streak Freeze**: premium'da her hafta otomatik 1 adet (max 2 birikir), free'de
  rozet/XP ile kazanılır. Kaçırılan gün için otomatik harcanır, kullanıcıya bildirilir.
- **Milestone**: 7, 12, 30, 100 gün → kutlama ekranı + bonus XP + rozet.
- **Hatırlatma push'u**: kullanıcının seçtiği saatte (17:00/20:00/22:00), *yalnızca o gün
  henüz çalışmadıysa*. Günde tek bildirim — sessiz kalma sözü tasarımda verilmiş, bozulmamalı.

---

## 4. İlerleme ve kilit açma

**Node tamamlanma eşiği**: `accuracy ≥ 80%`. Altında kalırsa node `completed` olmaz,
XP verilir (yarısı), tekrar denenebilir; yanlışlar `review_queue`'ya düşer.

**Kilit açma** (`unlock_rule` JSON → `UnlockRule` ağacı):
- Node: varsayılan `PreviousNodeCompleted`
- `unit_challenge`: `AllOf[unit içindeki tüm study node'ları completed]`
- Ünite: `PreviousUnitCompleted` (veya `MinCompletion(80)` — içerik ekibi seçer)
- Premium içerik: `RequiresPremium`
- Zorluk path'i (Kolay→…→SınavProvası): aynı ünite içinde `sort_order` + 
  `MinAccuracy(80)` zincirleriyle kurulur — ayrı bir mekanizma değil.

**Ünite yüzdesi** = tamamlanan node / toplam node (challenge'lar dahil).

**Adaptif zorluk (Faz 2)**: `user_topic_stats.mastery = weak` olan konulardan
`quick_review` node'u otomatik üretilir; oturum içi soru seçimi `%60 zayıf konu +
%40 normal havuz` ağırlığıyla yapılır (`ExerciseSelector` stratejisi — MVP'de
`RandomFromPool`, Faz 2'de `AdaptiveSelector`; arayüz aynı, OCP).

---

## 5. Lig

```php
'tiers' => ['bronze','silver','gold','emerald','sapphire','diamond','champion'],
'cohort_size' => 30,
'promotion_count' => 5,
'demotion_count' => 5,
'week_starts' => 'monday 00:00 Europe/Istanbul',
```

- Kullanıcı haftanın ilk XP'sini kazandığında bir lige **atanır** (lazy join) — hiç
  çalışmayan kullanıcı kohortu şişirmez.
- Kohort doldurma: aynı tier'da `member_count < 30` olan en eski aktif lig; yoksa yeni lig.
- Canlı sıralama Redis ZSET; `ZINCRBY` XP verilirken, `ZREVRANK` okuma anında.
- **Haftalık kapanış** (Pzt 00:00 TRT, kuyruk işi): her lig için sıralama kesinleşir,
  ilk 5 terfi, son 5 düşer, sonuç `league_memberships`'e yazılır, bildirim gönderilir.
  Job **idempotent**: `leagues.status = closed` kontrolü, chunk'lı işleme, yeniden
  çalıştırılabilir.
- Lig XP'si yalnızca oturum XP'sinden gelir; rozet/görev XP'si lige sayılmaz
  (manipülasyon yüzeyini daraltır).

---

## 6. Premium (Entitlement)

| Özellik | Free | Premium |
|---|---|---|
| Can | 5, 18 dk'da 1 dolar | Sınırsız |
| Reklam | Geçiş + ödüllü | Yok |
| Sınav takibi | 1 sınav | Sınırsız |
| Üniteler | Temel | `is_premium_only` dahil tümü |
| Challenge / Sınav provası | Haftada 3 | Sınırsız |
| Yanlış tekrar turu | Günde 1 | Sınırsız |
| Konu analizi | Özet | Detaylı |
| Streak freeze | Kazanılan | Haftalık otomatik |
| Offline (Faz 3) | — | Var |

**Satın alınamayanlar** (tasarımda açıkça yazıyor, uyulmalı): XP, seri, lig sıralaması.

**Teknik akış**: mağaza satın alması → RevenueCat → webhook → `subscription_events`
(idempotent) → `subscriptions` güncellenir → `EntitlementCache` invalidate. Her korumalı
endpoint `EnsureEntitlement` middleware'inden geçer. Grace period (ödeme hatası) boyunca
premium açık kalır — `status = grace`.

**Fiyat**: aylık ~99–129 ₺, yıllık ~599–799 ₺, yıllıkta 7 gün deneme. Fiyatlar sunucuda
değil mağazada tanımlı; API yalnızca ürün kimliği ve karşılaştırma tablosu döner.

---

## 7. Reklam

| Format | Yer | Kural |
|---|---|---|
| Ödüllü (rewarded) | Can bittiğinde, streak freeze kazanma | Kullanıcı isteğiyle, günde max 4, SSV zorunlu |
| Geçiş (interstitial) | Oturum bitiminden sonra | 2 oturumda 1, min 3 dk aralık, ilk 3 günde **hiç** gösterme |
| Banner | — | Kullanılmıyor (UX'i bozar) |

- Premium'da hiçbir reklam yok — kontrol sunucuda: `GET /me/ad-policy` istemciye
  ne göstereceğini söyler, istemci karar vermez.
- 18 yaş altı kullanıcıda çocuk odaklı reklam ayarları zorunlu (KVKK + Play Families).
- "İlk 3 gün reklamsız" kuralı retention için bilinçli; A/B testine açık config.

---

## 8. Hile ve suistimal önleme

| Vektör | Önlem |
|---|---|
| Cevap anahtarını istemciden okuma | Anahtar hiç gönderilmez, değerlendirme sunucuda |
| Bot / otomatik cevaplama | `elapsed_ms < min_answer_ms` (tipe göre 800–1500 ms) → `is_suspicious`, XP verilmez |
| İstek tekrarı ile XP çoğaltma | `Idempotency-Key` + `xp_ledger` unique kısıtı |
| Aynı node'u grind ederek lig yükseltme | Replay XP %50, `first_completion` tek sefer |
| Sahte reklam ödülü | AdMob SSV callback + `transaction_id` unique |
| Sahte premium | Yalnızca RevenueCat webhook'u yetki verir |
| Çoklu hesap ile lig | Cihaz parmak izi + yeni hesaplar bronze'dan başlar |
| Oturum süresini aşma | `expires_at`, sunucu tarafı kontrol, `410 SESSION_EXPIRED` |

Şüpheli davranış hesabı **kapatmaz**; işaretler, panelde raporlanır, XP'yi engeller.
Yanlış pozitifle öğrenci kaybetmek, birkaç hileciden pahalıdır.

---

## 9. İçerik yayın akışı

`draft` → `review` (branş editörü onayı) → `published` → (`archived`)

- Yayında olan içerik **silinmez**, `archived` olur — devam eden oturumlar `content_snapshot`
  sayesinde etkilenmez.
- Bir soru düzeltildiğinde `version++`; geçmiş `answer_attempts` eski sürüme referans verir,
  istatistik bozulmaz.
- Yayınlama `content_releases` kaydı üretir; mobil manifest sürümü buradan gelir.
- Soru kalite metriği: `exercise_stats` (doğru oranı, ortalama süre, atlanma) — %95+ doğru
  veya %10- doğru oranındaki sorular panelde "gözden geçir" olarak işaretlenir.
