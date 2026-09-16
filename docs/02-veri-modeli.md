# 02 · Veri Modeli

PostgreSQL 16. Tüm tablolarda `id` (bigint identity), `created_at`, `updated_at`.
Kullanıcıya görünen kaynaklarda ayrıca `uuid` (public id — enumerasyon saldırısını önler).

---

## 1. Müfredat ve içerik (Curriculum + Catalog)

> **Bu bölümün tam hâli ayrı dokümanda:**
> [`06-ders-ve-mufredat-sistemi.md`](06-ders-ve-mufredat-sistemi.md) — TYT/AYT'nin
> gerektirdiği esnek model, gerekçeleriyle. Aşağıda yalnızca tablo özeti var.

İki ayrı yapı, aralarında *eşleme* (içerme değil):

```
CURRICULUM — öğrencinin neye ihtiyacı var        CATALOG — elimizde ne var
  exams                 YKS                        subjects    Matematik
   └ exam_sections      TYT · AYT · YDT             └ topics    Türev, Limit …
   └ exam_variants      YKS-SAY · YKS-EA · …
        │                                          courses      TYT Matematik
        └── exam_variant_courses ─────────────────▶ └ units      Fonksiyonlar
            (eşleme tablosu)                           └ unit_nodes  (tarif)
   exam_blueprints      TYT Denemesi
                                                   exercises   (havuz → topic)
```

| Tablo | Anahtar kolonlar |
|---|---|
| `exams` | `code` (yks\|lgs\|kpss\|ales\|yds), `name`, `icon`, `is_active` |
| `exam_sections` | `exam_id`, `code` (tyt\|ayt\|ydt), `name`, `duration_min`, `question_count`, `sort_order` |
| `exam_variants` | `exam_id`, `code` (`yks_say`), `name`, `field_code` (say\|ea\|soz\|dil\|undecided) |
| `exam_variant_courses` | `exam_variant_id`, `course_id`, **`exam_section_id`** (sekme), `sort_order`, `is_required`, `access` (free\|premium), `exam_weight`, `placeholder_label?` |
| `exam_blueprints` | `exam_section_id?`, `exam_variant_id?`, `name`, `duration_min`, `scoring_rule` (jsonb) |
| `exam_blueprint_items` | `blueprint_id`, `course_id`\|`topic_id`, `question_count`, `difficulty_distribution` (jsonb) |
| `subjects` | `code`, `name`, `icon`, `color` — kavramsal ders, sınavdan bağımsız |
| `topics` | **`subject_id`**, `parent_id`, `code`, `name`, `grade_level`, `exam_weight` |
| `courses` | `subject_id`, `code` (`tyt_matematik`), `name`, `scope`, `icon`, `color`, `default_section_id`, `grade_range`, `effective_from_year?`, `status`, `published_at` |
| `units` | `course_id`, `title`, `sort_order`, `grade_level`, `difficulty_band`, `access`, `estimated_minutes`, `status` |
| `unit_topics` | `unit_id`, `topic_id`, `weight` |
| `unit_nodes` | `unit_id`, `title`, `node_type`, `difficulty`, `sort_order`, `exercise_count`, `time_limit_sec?`, `consumes_hearts`, `xp_reward`, `access`, **`selection_rule`** (jsonb), **`unlock_rule`** (jsonb), `status` |
| `exercises` | **`topic_id`**, `owner_course_id`, `owner_unit_id?`, `type`, `content` (jsonb), `answer_key` (jsonb), `explanation`, `difficulty` (1–5), `applicable_scopes` (jsonb), `media`, `status`, `version` |
| `unit_templates` | `code`, `name`, `nodes` (jsonb) — ünite iskeletini tek tıkla üretir |
| `content_releases` | `version`, `published_at`, `published_by`, `changelog`, `manifest_hash` |

**Üç kural, üçü de içerik kopyalamasını önlemek için:**

1. `topics` **subject'e** bağlıdır, course'a değil → TYT ve AYT Matematik aynı konu ağacını
   paylaşır; öğrencinin ustalığı TYT'den AYT'ye taşınır.
2. `exercises` **topic'e** bağlıdır, node'a değil → soru bir havuzdur; node onu bir
   `selection_rule` ile çağırır. Aynı soru Çalışma 2'de, Ünite Challenge'da ve Sınav
   Provasında tek kayıt olarak kullanılır.
3. `courses` **hiçbir varyanta ait değildir** → "TYT Temel Matematik" tek kayıttır;
   SAY, EA, SÖZ, DİL ve ileride DGS onu referans alır.

### Egzersiz içerik şemaları

`content` / `answer_key` her tip için ayrı JSON Schema ile doğrulanır (hem admin panelde
hem API'de). `answer_key` **hiçbir koşulda istemciye gitmez**.

```jsonc
// type: multiple_choice
"content": {
  "stem": "Orhun Yazıtları hangi Türk devletine aittir?",
  "options": [
    {"id":"a","text":"Asya Hun Devleti"},
    {"id":"b","text":"II. Göktürk Devleti"},
    {"id":"c","text":"Uygur Devleti"},
    {"id":"d","text":"Avarlar"}
  ],
  "shuffle": true
}
"answer_key": { "correct_option_id": "b" }

// type: matching
"content": { "left":[{"id":"1","text":"Kurultay"}], "right":[{"id":"x","text":"Meclis"}] }
"answer_key": { "pairs": {"1":"x","2":"y","3":"z","4":"w"}, "partial_credit": true }

// type: ordering
"content": { "instruction":"Eskiden yeniye sürükle.",
             "items":[{"id":"1","text":"Göktürkler"},{"id":"2","text":"Uygurlar"}] }
"answer_key": { "order": ["1","2","3"] }
```

Desteklenen tipler: `multiple_choice`, `fill_blank`, `matching`, `ordering`, `flashcard`,
`true_false`, `word_order`, `numeric_input`, `image_hotspot`, `diagram_label`.

## 2. Kullanıcı ve onboarding (Identity)

### `users`
`uuid`, `name` (lig sıralamasında görünen ad), `email?`, `password?`, `avatar_key`,
`is_guest`, `timezone` (varsayılan `Europe/Istanbul`), `locale`, `birth_year?`,
`parental_consent_at?` (LGS / 18 yaş altı — KVKK), `last_active_at`, `deleted_at`

Misafir girişi zorunlu: onboarding kayıt istemeden başlar ("Başla" → hesap sonradan bağlanır).

### `auth_identities`
`user_id`, `provider` (apple|google|email), `provider_user_id`, `email`
> Apple Sign-In App Store için zorunlu.

### `user_profiles` — onboarding çıktısı
`user_id`, `field` (sayisal|ea|sozel|dil|bilmiyorum), `grade` (9..12|mezun|8),
`target_exam_year`, `target_exam_month`, `daily_goal_rounds` (1|3|6),
`reminder_enabled`, `reminder_time` (17:00|20:00|22:00), `acquisition_source`,
`placement_completed_at?`

### `user_enrollments`
`user_id`, **`exam_variant_id`**, `is_primary`, `enrolled_at`, `field_changed_at?`
> Free: 1 varyant. Premium: sınırsız. Kısıt `Billing` entitlement'ı ile kontrol edilir.
>
> **Alan değişimi tek satırlık update'tir.** İlerleme tabloları `course_id`'ye bağlı
> olduğu için SAY → EA geçişinde TYT'nin tamamı ve ortak AYT dersleri korunur.

### `devices`
`user_id`, `platform`, `push_token`, `app_version`, `last_seen_at`

---

## 3. Öğrenme akışı (Learning)

### `study_sessions` — bir "tur"
`uuid`, `user_id`, `unit_node_id`, `status` (active|completed|abandoned|expired),
`consumes_hearts`, `time_limit_sec?`, `started_at`, `completed_at?`, `expires_at`,
`correct_count`, `wrong_count`, `accuracy`, `xp_awarded`, `is_perfect`,
`idempotency_key` (unique)

### `session_items` — oturuma seçilen sorular (anlık görüntü)
`study_session_id`, `exercise_id`, `exercise_version`, `position`,
`content_snapshot` (jsonb — soru sonradan düzenlense bile oturum tutarlı kalır),
`answered_at?`, `is_correct?`, `partial_score?`, `elapsed_ms?`, `is_suspicious`

> Snapshot **zorunlu**, opsiyonel bir iyileştirme değil: node'lar sabit soru listesi
> değil `selection_rule` tutar, yani aynı node her açılışta farklı soru getirebilir.
> Oturumun soruları başlangıçta dondurulur; içerik ekibi araya düzeltme yapsa bile
> devam eden oturum bozulmaz ve sonuç ekranı tutarlı kalır.

### `answer_attempts` — analiz için append-only
`user_id`, `exercise_id`, `topic_id`, `study_session_id`, `is_correct`,
`partial_score`, `elapsed_ms`, `answered_at`
> Konu bazlı zayıf/güçlü analizi ve adaptif zorluk bu tablodan beslenir.
> Aylık partition (tarih bazlı) — hacim büyüyecek.

### `user_node_progress`
`user_id`, `unit_node_id`, `state` (locked|available|completed), `best_accuracy`,
`attempts`, `is_perfect`, `completed_at?`, `last_attempt_at`
> unique(`user_id`,`unit_node_id`)

### `user_unit_progress`
`user_id`, `unit_id`, `completion_percent`, `completed_nodes`, `total_nodes`, `completed_at?`

### `user_course_progress`
`user_id`, **`course_id`**, `level`, `xp`, `completed_units`, `total_units`, `last_studied_at`
> UI'daki "TYT Tarih · Lv 6 · 2/8 ünite · 650 XP" kartı doğrudan buradan.
> `exam_variant_id` **yok** — ilerleme derse aittir, öğrencinin alanına değil.

### `user_topic_stats` — zayıf/güçlü konu (materialized, nightly + incremental)
`user_id`, `topic_id`, `attempts`, `correct`, `accuracy`, `mastery`
(`weak` <%50, `developing` %50-79, `strong` ≥%80), `last_practiced_at`
> `topic_id` subject seviyesinde olduğu için TYT ve AYT ustalığı **birleşik** sayılır —
> öğrenci TYT'de öğrendiği "Bileşke Fonksiyon"u AYT'ye geçtiğinde yeniden öğrenmez.

### `review_queue` — "Hızlı Tekrar" ve yanlış tekrarı
`user_id`, `exercise_id`, `topic_id`, `due_at`, `interval_days`, `ease`, `lapses`
> Faz 2'de SM-2 benzeri aralıklı tekrar; MVP'de basitçe "son 7 günün yanlışları".

### `placement_attempts` — seviye tespiti (onboarding adım 6)
`user_id`, `exam_variant_id`, `course_id`, `score`, `topic_scores` (jsonb),
`recommended_unit_id`, `recommended_node_id`, `completed_at`
> Can harcamaz. Sonuç `user_topic_stats`'a ilk tohum olarak yazılır — 4 soruluk bir
> test bile "nereden başlayalım" sorusunu veriye dayandırır.

---

## 4. Oyunlaştırma (Gamification)

### `xp_ledger` — tek doğruluk kaynağı, append-only
`user_id`, `amount`, `source_type` (session|challenge|badge|quest|placement),
`source_id`, `course_id?`, `topic_id?`, `counts_for_league` (bool), `awarded_at`,
**unique(`source_type`,`source_id`)** → idempotency garantisi.

> Toplamlar (`user_stats.total_xp`) bu defterden türetilir; tutarsızlık şüphesinde
> yeniden inşa edilebilir. Retry/çift istek asla çift XP üretmez.

### `user_stats`
`user_id`, `total_xp`, `level`, `current_streak`, `longest_streak`,
`last_study_date` (kullanıcının saat dilimine göre yerel tarih), `total_study_minutes`,
`total_correct`, `total_sessions`, `perfect_sessions`

### `streak_freezes`
`user_id`, `source` (premium|purchase|reward), `used_on?`, `expires_at?`

### `badges` / `user_badges`
`badges`: `code`, `name`, `description`, `icon`, `criteria` (jsonb), `tier`, `is_active`
`user_badges`: `user_id`, `badge_id`, `earned_at`, `progress` (jsonb)
> UI'daki rozetler: İlk Çalışma, 7 Gün, 100 Doğru, İlk Ünite, Tarih Lv5, 30 Gün, 5.000 XP, Perfect.
> Kriterler `BadgeCriterion` strateji sınıflarına eşlenir (OCP) — yeni rozet = yeni sınıf + kayıt.

### `daily_quests` / `user_daily_quests` (Faz 2)
`code`, `template` (jsonb), `xp_reward` · `user_id`, `quest_id`, `date`, `progress`, `target`, `completed_at?`

---

## 5. Can sistemi (Hearts)

### `user_hearts`
`user_id` (pk), `hearts` (0–5), `last_regen_at`, `unlimited_until?`

> **Cron yok.** Bakiye okunurken hesaplanır:
> `hearts = min(max, hearts + floor((now - last_regen_at) / regen_interval))`
> Bu, 100k kullanıcıyı dakikada güncelleyen bir job'dan hem ucuz hem doğru.

### `heart_transactions` — denetim izi
`user_id`, `delta`, `reason` (wrong_answer|regen|ad_reward|premium|purchase|admin_grant|refill),
`balance_after`, `reference_type?`, `reference_id?`, `created_at`

---

## 6. Lig (League)

### `leagues`
`tier` (bronze|silver|gold|emerald|sapphire|diamond|champion), `week_start` (pazartesi),
`capacity` (30), `member_count`, `status` (active|closed), `closed_at?`

### `league_memberships`
`league_id`, `user_id`, `weekly_xp`, `final_rank?`, `result` (promoted|demoted|stayed)?,
`joined_at` · unique(`league_id`,`user_id`), unique(`user_id`,`week_start`)

> Canlı sıralama Redis ZSET'te (`league:{id}:xp`); DB'ye dakikalık ve hafta kapanışında
> yazılır. Okuma Redis'ten (O(log n)), kaynak doğruluk DB'de.

---

## 7. Abonelik ve reklam

### `subscriptions`
`user_id`, `provider` (revenuecat), `provider_subscription_id`, `product_id`,
`plan` (monthly|yearly), `status` (trial|active|grace|expired|cancelled|refunded),
`started_at`, `current_period_end`, `trial_end?`, `store` (app_store|play_store)

### `subscription_events` — webhook ham kaydı (idempotent)
`provider_event_id` (unique), `type`, `payload` (jsonb), `processed_at?`, `error?`

### `ad_rewards`
`user_id`, `placement` (heart_refill|streak_freeze), `network` (admob),
`transaction_id` (unique — AdMob SSV), `verified_at?`, `granted_at?`, `reward_payload` (jsonb)
> Günlük limit (`config/tekrarla/ads.php`: max 4/gün) burada sayılır.

### `analytics_events`
`user_id?`, `name`, `properties` (jsonb), `occurred_at`, `exported_at?`
> Kuyrukla Amplitude/Mixpanel'e aktarılır; tablo buffer görevi görür.

### `audit_logs` (Admin)
`admin_user_id`, `action`, `entity_type`, `entity_id`, `before` (jsonb), `after` (jsonb), `ip`
> İçerik değişikliklerinin izlenebilirliği — çok yazarlı CMS'te şart.

---

## 8. Kritik indeksler

```sql
-- sıcak yol: oturum akışı
CREATE INDEX ON session_items (study_session_id, position);
CREATE UNIQUE INDEX ON study_sessions (idempotency_key);
CREATE INDEX ON study_sessions (user_id, status, started_at DESC);

-- ders listesi ekranı (TYT/AYT sekmeleri — her açılışta)
CREATE UNIQUE INDEX ON exam_variant_courses (exam_variant_id, course_id);
CREATE INDEX ON exam_variant_courses (exam_variant_id, exam_section_id, sort_order);
CREATE UNIQUE INDEX ON user_course_progress (user_id, course_id);

-- yol ekranı
CREATE UNIQUE INDEX ON user_node_progress (user_id, unit_node_id);
CREATE INDEX ON user_node_progress (user_id, unit_node_id) INCLUDE (state, best_accuracy);
CREATE INDEX ON units (course_id, sort_order) WHERE status = 'published';
CREATE INDEX ON unit_nodes (unit_id, sort_order) WHERE status = 'published';

-- SORU HAVUZU SEÇİMİ — en sıcak sorgu, her oturum başlangıcında çalışır
CREATE INDEX ON exercises (topic_id, difficulty, type)
  WHERE status = 'published';
CREATE INDEX ON exercises USING gin (applicable_scopes jsonb_path_ops);
CREATE INDEX ON unit_topics (unit_id) INCLUDE (topic_id, weight);
-- "son 14 günde görülen" filtresi için:
CREATE INDEX ON answer_attempts (user_id, answered_at DESC) INCLUDE (exercise_id);

-- analiz
CREATE INDEX ON answer_attempts (user_id, topic_id, answered_at DESC);
CREATE INDEX ON answer_attempts (exercise_id, answered_at DESC);
CREATE UNIQUE INDEX ON user_topic_stats (user_id, topic_id);

-- gamification
CREATE UNIQUE INDEX ON xp_ledger (source_type, source_id);
CREATE INDEX ON xp_ledger (user_id, awarded_at DESC);

-- lig
CREATE UNIQUE INDEX ON league_memberships (league_id, user_id);
CREATE INDEX ON league_memberships (league_id, weekly_xp DESC);
```

## 9. Saklama ve KVKK

- `answer_attempts`: 24 ay, sonra topic bazında toplanıp anonimleştirilir.
- `analytics_events`: 90 gün (dışa aktarıldıktan sonra silinir).
- Hesap silme: `users.deleted_at` + 30 gün sonra hard-delete/anonimleştirme job'ı
  (lig geçmişi ve XP defteri `user_id` yerine anonim rumuzla kalır).
- 18 yaş altı (LGS segmenti): `parental_consent_at` dolu değilse reklam kişiselleştirme
  kapalı (AdMob `tagForChildDirectedTreatment`), veri işleme kısıtlı.
