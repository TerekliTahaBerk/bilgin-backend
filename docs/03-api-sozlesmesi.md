# 03 · API Sözleşmesi

Taban: `https://api.tekrarla.app/v1` · Auth: `Authorization: Bearer <sanctum-token>`
Tüm mutasyonlarda: `Idempotency-Key: <uuid>` · Sürümleme URL'de (`/v1`).

---

## 1. Yanıt zarfı

```jsonc
// başarı
{ "data": { ... }, "meta": { "server_time": "2026-09-14T10:00:00+03:00" } }

// hata
{ "error": { "code": "HEARTS_DEPLETED",
             "message": "Canların bitti.",
             "details": { "next_heart_at": "2026-09-14T10:18:00+03:00" } } }
```

`message` kullanıcıya gösterilebilir Türkçe metindir; istemci `code`'a göre dallanır,
metne göre değil.

### Hata kodları

| Kod | HTTP | Anlam |
|---|---|---|
| `UNAUTHENTICATED` / `TOKEN_EXPIRED` | 401 | |
| `FORBIDDEN` | 403 | |
| `PREMIUM_REQUIRED` | 402 | Kilitli ders/ünite/challenge |
| `NODE_LOCKED` | 409 | Önceki adım tamamlanmamış |
| `HEARTS_DEPLETED` | 409 | Can yok — `next_heart_at` döner |
| `SESSION_ALREADY_COMPLETED` | 409 | |
| `SESSION_EXPIRED` | 410 | Süre limiti aşıldı |
| `ANSWER_ALREADY_SUBMITTED` | 409 | Idempotent tekrar → orijinal sonuç döner |
| `CONTENT_OUTDATED` | 409 | İstemci eski içerik sürümüyle oturum açmaya çalıştı |
| `ENROLLMENT_LIMIT` | 409 | Free planda 2. sınava kayıt |
| `POOL_INSUFFICIENT` | 503 | Node'un `selection_rule`'u yeterli soru getiremedi (yayın kapısı kaçırmışsa) — alarm üretir |
| `AD_REWARD_LIMIT` | 429 | Günlük reklam ödülü limiti |
| `VALIDATION_ERROR` | 422 | `details.fields` |
| `RATE_LIMITED` | 429 | |

---

## 2. Kimlik ve onboarding

| Method | Endpoint | Not |
|---|---|---|
| `POST` | `/auth/guest` | Cihaz kimliğiyle misafir hesap — onboarding kayıt istemez |
| `POST` | `/auth/social` | `{provider: apple\|google, id_token}` |
| `POST` | `/auth/email/register` · `/auth/email/login` | |
| `POST` | `/auth/link` | Misafir hesabı kalıcı hesaba bağlar (ilerleme korunur) |
| `POST` | `/auth/logout` · `/auth/refresh` | |
| `DELETE` | `/auth/account` | KVKK hesap silme talebi |

```http
POST /v1/onboarding
{
  "exam_code": "yks",
  "field": "say",
  "grade": "11",
  "target_exam_year": 2027,
  "name": "Ege",
  "avatar_key": "dino_green",
  "acquisition_source": "instagram",
  "daily_goal_rounds": 3,
  "reminder_time": "20:00"
}
→ 201 { "data": { "user": {...},
                  "enrollment": { "exam_variant": {"code":"yks_say","name":"YKS · Sayısal"},
                                  "course_count": 13 },
                  "next_step": "placement" } }
```

> `exam_code` + `field` sunucuda tek bir `exam_variant`'a çözülür (`yks` + `say` →
> `yks_say`). "Henüz bilmiyorum" → `field: "undecided"` → `yks_undecided` (yalnızca TYT).
> Alan sonradan `PATCH /me/enrollment {"field":"ea"}` ile değişir; **ilerleme korunur**,
> yalnızca ders listesi güncellenir.

**Seviye tespiti** (atlanabilir, can harcamaz):
`POST /v1/placement/start` → `{placement_id, exercises[]}` ·
`POST /v1/placement/{id}/submit` → `{score, recommended_unit, start_at_node}`

---

## 3. İçerik (Catalog) — okuma, agresif cache

Tüm GET'lerde `ETag` + `Cache-Control`; istemci `If-None-Match` ile 304 alır.

| Method | Endpoint | Döndürdüğü |
|---|---|---|
| `GET` | `/content/manifest` | `{version, courses:[{id, hash}], ...}` — delta indirme |
| `GET` | `/exams` | Sınav + alan (varyant) ağacı — onboarding adım 1–2 |
| `GET` | `/me/enrollments` | Kayıtlı varyantlar |
| `POST` | `/me/enrollments` | Sınav ekle (free'de `ENROLLMENT_LIMIT`) |
| `PATCH` | `/me/enrollment` | **Alan değiştir** (SAY→EA) — ilerleme korunur |
| `GET` | `/me/courses` | **Ana sayfa + Öğren ekranı** — TYT/AYT sekmeleriyle gruplu ders listesi |
| `GET` | `/courses/{id}/path` | **Ünite yolu (imza ekran)** |
| `GET` | `/nodes/{id}` | Node önizleme: soru adedi, süre, XP, durum |

```jsonc
// GET /v1/me/courses   → tasarımdaki TYT / AYT sekmesi
{"data":{
  "exam_variant":{"code":"yks_say","name":"YKS · Sayısal"},
  "sections":[
    {"code":"tyt","name":"TYT","courses":[
      {"id":12,"code":"tyt_tarih","name":"Tarih","color":"#14976B","icon":"...",
       "level":6,"xp":650,"completed_units":2,"total_units":8,"access":"free"},
      {"id":11,"code":"tyt_matematik","name":"Matematik","level":5,
       "completed_units":3,"total_units":9,"access":"free"},
      {"id":18,"code":"tyt_din","name":"Din Kültürü","access":"premium",
       "locked":true,"lock_reason":"PREMIUM_REQUIRED"}]},
    {"code":"ayt","name":"AYT","courses":[
      {"id":31,"code":"ayt_matematik","name":"Matematik","level":1,
       "completed_units":0,"total_units":2,"access":"free"},
      {"id":32,"code":"ayt_fizik","name":"Fizik","status":"coming_soon",
       "placeholder_label":"Yakında"}]}]}}
```

```jsonc
// GET /v1/courses/12/path
{"data":{
  "course":{"id":12,"code":"tyt_tarih","name":"TYT Tarih","color":"#14976B",
            "level":6,"xp":650,"completed_units":2,"total_units":8,
            "path_strategy":"grade_aware"},
  "units":[
    {"id":101,"title":"Tarih ve Zaman","order":1,"completion_percent":100,
     "state":"completed","completed_nodes":3,"total_nodes":3,
     "nodes":[
       {"id":1001,"title":"Çalışma 1","type":"study","difficulty":"kolay",
        "state":"completed","best_accuracy":100,"is_perfect":true,"xp_reward":10},
       {"id":1003,"title":"Mini Challenge","type":"mini_challenge",
        "state":"available","exercise_count":10,"xp_reward":60}]},
    {"id":102,"title":"İlk ve Orta Çağlarda Türk Dünyası","order":2,
     "completion_percent":60,"state":"in_progress",
     "nodes":[
       {"id":1010,"title":"Kronolojik Sırala","type":"study","difficulty":"orta",
        "state":"available","exercise_count":7,"estimated_minutes":4,"xp_reward":20},
       {"id":1011,"title":"Kut ve Töre","type":"study","difficulty":"orta",
        "state":"locked","lock_reason":"PREVIOUS_NODE_INCOMPLETE",
        "preview":"boşluk doldur · 6 soru"},
       {"id":1013,"title":"Ünite Challenge","type":"unit_challenge",
        "state":"locked","lock_reason":"UNIT_INCOMPLETE","xp_reward":100}]},
    {"id":103,"title":"Orta Çağ'da Dünya","order":3,"state":"locked",
     "lock_reason":"PREVIOUS_UNIT_INCOMPLETE","lock_hint":"Ünite 2 bitince açılır"}
  ]}}
```

`lock_reason` enum → istemci metni kendi gösterir (`PREVIOUS_NODE_INCOMPLETE`,
`PREVIOUS_UNIT_INCOMPLETE`, `PREMIUM_REQUIRED`, `LEVEL_TOO_LOW`).

> Ünite sırası **kullanıcıya özeldir**: `PathStrategy` öğrencinin sınıfına (9–12/mezun)
> ve hedef yılına göre sıralar. İçerik tek, görünüm kişiye göre — 10. sınıf öğrencisi
> ile mezun aynı dersi farklı sırada görür.

### Sınav provası / deneme

| Method | Endpoint | |
|---|---|---|
| `GET` | `/blueprints?exam_variant=yks_say` | Mevcut denemeler (TYT Genel, AYT-SAY…) |
| `POST` | `/sessions` `{blueprint_id}` | Blueprint'ten oturum — normal oturumla aynı akış |

Sonuç ekranında blueprint'in `scoring_rule`'una göre **net ve puan tahmini** döner
(YKS: yanlış 1/4 götürür). Sınav provası can harcamaz.

---

## 4. Çalışma oturumu — ürünün kalbi

### Oturum başlat
```http
POST /v1/sessions
Idempotency-Key: 0b7f...
{ "node_id": 1010, "content_version": 42 }

→ 201
{"data":{
  "session_id":"9f1c-...","node":{"id":1010,"title":"Kronolojik Sırala","type":"study"},
  "consumes_hearts":true,"hearts":5,"time_limit_sec":null,
  "expires_at":"2026-09-14T10:30:00+03:00",
  "items":[
    {"position":1,"exercise_id":"e-771","type":"multiple_choice",
     "content":{"stem":"Orhun Yazıtları hangi Türk devletine aittir?",
                "options":[{"id":"a","text":"Asya Hun Devleti"},
                           {"id":"b","text":"II. Göktürk Devleti"},
                           {"id":"c","text":"Uygur Devleti"},
                           {"id":"d","text":"Avarlar"}]}},
    {"position":2,"exercise_id":"e-772","type":"fill_blank",
     "content":{"template":"Yazısız hukuk kurallarına {{0}} denir.",
                "choices":["Töre","Kurultay","Toy"]}}
  ]}}
```

`answer_key` ve `explanation` **yanıtta yoktur**. Can yoksa `409 HEARTS_DEPLETED`.

### Cevap gönder (her soru için ayrı istek)
```http
POST /v1/sessions/9f1c-.../answers
Idempotency-Key: 3d2a-...
{ "exercise_id":"e-771", "answer":{"option_id":"b"}, "elapsed_ms":4200 }

→ 200
{"data":{
  "is_correct":true,"partial_score":1.0,
  "correct_answer":{"option_id":"b"},
  "explanation":"Göktürk alfabesiyle yazıldı.",
  "xp_delta":10,"hearts":5,"combo":3,
  "progress":{"answered":1,"total":7}}}

// yanlışta
{"data":{"is_correct":false,"correct_answer":{"option_id":"b"},
         "explanation":"...","xp_delta":0,"hearts":4,"combo":0,
         "hearts_depleted":false}}
```

Cevap formatları tipe göre: `{"option_id":"b"}` · `{"blanks":["Töre"]}` ·
`{"pairs":{"1":"x","2":"y"}}` · `{"order":["1","2","3"]}` · `{"value":42}` ·
`{"known":true}` (flashcard) · `{"words":["I","went","home"]}`

### Oturumu tamamla
```http
POST /v1/sessions/9f1c-.../complete
→ 200
{"data":{
  "score":{"correct":8,"total":10,"accuracy":80,"is_perfect":false},
  "xp":{"base":20,"bonuses":[{"code":"first_completion","amount":60}],"total":80},
  "level":{"before":6,"after":6,"current_xp":730,"next_level_xp":1000,"leveled_up":false},
  "streak":{"days":12,"extended_today":true,"milestone":null},
  "unit":{"id":102,"completion_percent":46},
  "unlocked_nodes":[{"id":1011,"title":"Kut ve Töre"}],
  "badges_earned":[],
  "league":{"tier":"emerald","rank":8,"weekly_xp":2450,"rank_change":1},
  "hearts":4}}
```

Bu tek yanıt, tasarımdaki **sonuç ekranı + yol ekranındaki "yeni node açıldı"**
animasyonlarının tamamını besler — ekstra istek gerekmez.

`POST /v1/sessions/{id}/abandon` — kullanıcı çıkarsa (XP yok, harcanan can iade edilmez).

---

## 5. Can

| Method | Endpoint | |
|---|---|---|
| `GET` | `/hearts` | `{hearts, max, unlimited, next_heart_at, full_at}` |
| `POST` | `/hearts/practice-refill` | "Pratik yaparak 1 can kazan" — can harcamayan tekrar turu açar |
| `POST` | `/ads/reward` | İstemci bildirimi; ödül **SSV doğrulanınca** verilir |
| `POST` | `/webhooks/admob/ssv` | AdMob server-side verification (imza doğrulamalı, auth'suz) |

---

## 6. Lig, profil, rozet

| Method | Endpoint | |
|---|---|---|
| `GET` | `/league/current` | 30 kişilik tablo, yükselme/düşme bölgesi, `ends_at` |
| `GET` | `/league/history` | Geçmiş haftalar |
| `GET` | `/me/stats` | XP, level, seri, en uzun seri, toplam çalışma |
| `GET` | `/me/topics?course_id=` | Zayıf/güçlü konu analizi (free: özet, premium: detay) |
| `GET` | `/me/badges` | Kazanılan + ilerlemedeki rozetler |
| `GET` | `/quests/daily` | Günlük görevler (Faz 2) |
| `PATCH` | `/me/settings` | Hedef, hatırlatma saati, bildirim, dil |
| `POST` | `/me/devices` | Push token kaydı |

```jsonc
// GET /v1/league/current
{"data":{"tier":"emerald","name":"Zümrüt Lig","ends_at":"2026-09-21T00:00:00+03:00",
  "promotion_zone":5,"demotion_zone":5,"my_rank":8,"xp_to_promotion":700,
  "members":[{"rank":1,"name":"Deniz","avatar_key":"...","weekly_xp":4820,"streak":18},
             {"rank":8,"name":"Ege","weekly_xp":2450,"streak":12,"is_me":true}]}}
```

---

## 7. Premium

| Method | Endpoint | |
|---|---|---|
| `GET` | `/premium/offerings` | RevenueCat paketleri + free/premium karşılaştırma tablosu (sunucudan, mağaza fiyatı SDK'dan) |
| `GET` | `/me/entitlements` | `{premium:{active, plan, expires_at, in_trial}}` |
| `POST` | `/webhooks/revenuecat` | İmza doğrulamalı, idempotent (`provider_event_id`) |

Premium durumu **yalnızca** webhook + RevenueCat doğrulamasıyla belirlenir; istemcinin
"ben premium'um" demesi hiçbir kapıyı açmaz.

---

### Denemede cevap yanıtı farklıdır

`POST /sessions/{id}/answers` çalışma turunda `is_correct`, `partial_score`,
`correct_answer` ve `explanation` döndürür. **Deneme (sınav provası)
oturumlarında bu dört alan hiç gönderilmez**; yalnızca `progress` kalır.

Sebep: sınav provasında her sorudan sonra sonucu görmek denemenin amacını
bozar. İstemcinin göstermemesi yetmez — yanıtta duran cevap anahtarı, araya
giren biri tarafından okunabilir. Sonuç `POST /sessions/{id}/complete`
yanıtındaki `exam` alanında (net, doğru/yanlış/boş, tahmini puan) dönüyor.

## 8. Admin API (`/admin/v1`) — Next.js panel

Ayrı guard, rol bazlı (`spatie/laravel-permission`): `super_admin`, `content_editor`,
`content_reviewer`, `support`, `analyst`.

- `GET|POST|PATCH|DELETE` `/exams`, `/exam-sections`, `/exam-variants`, `/subjects`,
  `/topics`, `/courses`, `/units`, `/nodes`, `/exercises`, `/blueprints`
- `PUT /exam-variants/{id}/courses` — ders eşlemesi (sekme, sıra, free/premium) — **kod deploy'u gerektirmeden alan müfredatı düzenleme**
- `POST /units` `{template_code, topic_ids}` — şablondan 6 node'u kural ve XP'siyle üretir
- `POST /exercises/bulk-import` — Excel/CSV toplu soru girişi (binlerce soru gerçeği)
- `POST /exercises/{id}/validate` — tipe göre JSON şema doğrulaması (panelde anlık önizleme)
- `GET /units/{id}/nodes` — ünitenin adımları (kimlik, sıra, gereken soru sayısı); panelin yayın hazırlığı bloğu önce bunu okur, sonra her adım için kural önizlemesi çağırır
- `GET /curriculum/options` — eşleme ekranının varyant ve sınav oturumu seçenekleri; panelde sabit kimlik yazmayı gereksiz kılar
- `GET /nodes/{id}/preview-selection` — **`selection_rule` kuru çalıştırma**. İKİ sayı döner: `available`/`passes` yayın kararını verir (ünitenin kendi taslakları aday sayılır), `live_available`/`live_passes` öğrencinin ŞU AN aldığını söyler. Ayrıştıklarında `live_warning` dolu gelir — yayınlanmış bir ünitede soru arşivlenip yerine taslak yazıldığında aday yeterli görünürken öğrenciye giden azalır; panel bu uyarıyı göstermeli
- `POST /units/{id}/submit-review` → `POST /units/{id}/publish` — draft → review → published. Yayın doğrulaması ünitenin KENDİ arşivlenmemiş sorularını aday sayar (aksi hâlde ilk yayın imkânsızdı); arşivlenmiş sorular ne aday olur ne de yayına döner
- `POST /content/releases` — sürüm yayınla, manifest hash üret
- `GET /users`, `GET /users/{id}` (destek ekranı), `POST /users/{id}/grant-hearts`
- `GET /analytics/overview` — DAU, tamamlama oranı, reklam/dönüşüm hunisi
- `GET /audit-logs`

---

## 9. Kesişen kurallar

- **Rate limit**: `/auth/*` 10/dk/IP · `/sessions/*/answers` 120/dk/kullanıcı · genel 300/dk.
- **Idempotency**: `Idempotency-Key` → Redis'te 24 saat; tekrar edilen istek aynı yanıtı
  (aynı HTTP kodu ile) döner, yan etki üretmez.
- **Saat dilimi**: sunucu UTC saklar, yanıtlarda kullanıcının `timezone`'una göre ISO-8601
  offset'li döner. Seri/günlük hedef hesabı kullanıcı yerel gününe göredir.
- **Sözleşme kaynağı**: OpenAPI 3.1 (`docs/openapi.yaml`), Scramble ile koddan üretilir;
  Flutter istemcisi `openapi-generator` ile tip güvenli client alır. Sözleşme değişimi
  CI'da diff kontrolünden geçer.
