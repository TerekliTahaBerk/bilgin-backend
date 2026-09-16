# 06 · Esnek Ders ve Müfredat Sistemi

> Bu doküman `02-veri-modeli.md` §1'in yerini alır. MVP kapsamı: **YKS · TYT + AYT**.

---

## 1. Problem: TYT/AYT neden basit bir "sınav → ders" listesi değil

Tek bir örnek üzerinden bakalım — **Matematik**:

| Bağlam | Gerçek |
|---|---|
| TYT Temel Matematik | 40 soru, 9–10. sınıf ağırlıklı, herkes için zorunlu |
| AYT Matematik | 40 soru, 11–12. sınıf, **sadece** Sayısal ve EA öğrencisi için |
| Aynı konu (Türev) | AYT'de var, TYT'de yok — ama öğrencinin "Türev" ustalığı tek bir gerçek |
| Sıralama | 10. sınıf öğrencisi için "Limit" daha sonra, 12. sınıf için hemen |
| Alan değişimi | Öğrenci Kasım'da Sayısal'dan EA'ya geçiyor — TYT ilerlemesi kaybolmamalı |

Naif model (`units.exam_track_id` → TYT / AYT-SAY / AYT-EA) bunların **hepsinde** çuvallar:

- AYT Matematik hem SAY hem EA'da olduğu için **iki kez girilir** → içerik ekibi aynı
  soruyu iki yere yazar, düzeltmeyi iki yerde yapar, istatistik ikiye bölünür.
- Alan değiştiren öğrencinin ilerlemesi başka bir track'e ait olduğu için **sıfırlanır**.
- "Türev" ustalığı TYT ve AYT'de ayrı sayılır → zayıf konu analizi yalan söyler.
- Yeni bir sınav (DGS, ALES — ikisi de TYT matematiğinin %80'i) eklemek **yine kopyalama** demek.

20.000 soruluk bir operasyonda kopyalama, projeyi batıran tek şeydir. O yüzden model
şu ayrım üzerine kuruldu:

> **Müfredat** (öğrencinin neye ihtiyacı var) ile **İçerik** (elimizde ne var) ayrı
> yapılardır ve birbirine *eşleme* ile bağlanır, *içerme* ile değil.

---

## 2. Model

```
┌─ Curriculum modülü ────────────┐        ┌─ Catalog modülü ───────────────┐
│                                │        │                                │
│  exams            YKS          │        │  subjects      Matematik       │
│   └ exam_sections TYT, AYT,YDT │        │   └ topics     Türev, Limit …  │
│   └ exam_variants YKS-SAY …    │        │                                │
│        │                       │        │  courses       TYT Matematik   │
│        └── exam_variant_courses ├───────▶│   └ units      Fonksiyonlar    │
│            (eşleme tablosu)    │        │      └ unit_nodes  (tarif)     │
│                                │        │                                │
│  exam_blueprints  TYT Denemesi │        │  exercises  (havuz, topic'e    │
│   └ blueprint_items            │        │              bağlı)            │
└────────────────────────────────┘        └────────────────────────────────┘
```

### 2.1 `subjects` — kavramsal ders
`code`, `name`, `icon`, `color`
> Matematik, Türkçe, Tarih, Fizik… Sınavdan bağımsız. **Konular (`topics`) buraya asılır.**

### 2.2 `courses` — bir dersin belirli bir kapsamdaki müfredatı
`subject_id`, `code` (`tyt_matematik`), `name` ("TYT Temel Matematik"), `scope` (tyt|ayt|ydt|lgs|kpss),
`icon`, `color`, `default_section_id`, `grade_range` (jsonb), `status`, `published_at`

> İçerik **burada** yaşar. Course hiçbir sınav varyantına ait değildir — bağımsızdır.
> "TYT Temel Matematik" tek bir kayıttır; SAY, EA, SÖZ, DİL, hatta ileride DGS onu
> *referans alır*, kopyalamaz.

### 2.3 `exam_sections` — sınav oturumu (üstteki sekme)
`exam_id`, `code` (tyt|ayt|ydt), `name`, `sort_order`, `duration_min`, `question_count`
> Tasarımdaki **TYT / AYT sekmesi** doğrudan buradan gelir. LGS'de `sozel`/`sayisal`.

### 2.4 `exam_variants` — öğrencinin kaydolduğu şey
`exam_id`, `code` (`yks_say`), `name` ("YKS · Sayısal"), `field_code` (say|ea|soz|dil|undecided),
`description`, `sort_order`, `is_active`

| Varyant | Onboarding'de karşılığı |
|---|---|
| `yks_say` | "Sayısal · Matematik · Fizik · Kimya · Biyoloji" |
| `yks_ea` | "Eşit Ağırlık" |
| `yks_soz` | "Sözel" |
| `yks_dil` | "Dil" |
| `yks_undecided` | "Henüz bilmiyorum → TYT dersleriyle başlarız" |

> Onboarding'in 1. ve 2. adımı (sınav + alan) **tek bir `exam_variant`'a** çözülür.

### 2.5 `exam_variant_courses` — sistemin kalbi (eşleme tablosu)

| Kolon | Ne işe yarar |
|---|---|
| `exam_variant_id` | |
| `course_id` | |
| `exam_section_id` | **Hangi sekmede görünecek** (TYT / AYT) — course'ta değil burada, çünkü aynı course farklı sınavda farklı sekmede olabilir |
| `sort_order` | Alana göre sıralama — "Derslerini buna göre sıralayacağım" |
| `is_required` | Zorunlu / opsiyonel (KPSS Eğitim Bilimleri gibi) |
| `access` | `free` \| `premium` → "Din Kültürü · Premium ile açılır" |
| `exam_weight` | Bu varyantta kaç soru geliyor (net/puan tahmini ve öneri motoru için) |

**Sonuç:** `YKS-SAY` = 9 TYT course + 4 AYT course = 13 satır. `YKS-EA` = 9 TYT course +
4 farklı AYT course. **TYT Matematik kaydı ikisinde de aynı satırı işaret eder.**

#### Bunun kazandırdıkları (kod yazmadan)

| İhtiyaç | Çözüm |
|---|---|
| Alan değiştirme (SAY → EA) | `user_enrollments.exam_variant_id` güncellenir. İlerleme `course_id`'ye bağlı olduğu için **TYT'nin tamamı ve AYT Matematik korunur**, sadece ders listesi değişir |
| Birden fazla sınav (premium) | Yeni enrollment satırı; ortak course'lar tek ilerleme paylaşır |
| Yeni sınav (DGS) | Yeni `exam` + `exam_variants` + eşleme satırları. **Sıfır yeni içerik** — mevcut TYT course'ları yeniden kullanılır |
| Ders paywall'ı | `access` kolonu |
| "Henüz bilmiyorum" | Sadece TYT course'larıyla eşlenmiş varyant |

---

## 3. İçerik tarafı: havuz + tarif

### 3.1 `topics` — konu ağacı, **subject'e** bağlı (course'a değil)
`subject_id`, `parent_id`, `code`, `name`, `grade_level`, `exam_weight`

```
Matematik
 └ Fonksiyonlar
    ├ Fonksiyon Kavramı      (grade 10, TYT)
    ├ Bileşke Fonksiyon      (grade 10, TYT+AYT)
    └ Türev                  (grade 12, AYT)
```

> Kritik: topic **subject seviyesindedir**. Böylece TYT Matematik ve AYT Matematik
> aynı konu ağacını paylaşır → öğrencinin "Bileşke Fonksiyon" ustalığı tek yerde birikir,
> TYT'den AYT'ye geçerken bilgisi **taşınır**. Zayıf/güçlü analizi de tek ve doğru olur.

### 3.2 `units` — müfredat bölümü
`course_id`, `title`, `sort_order`, `grade_level`, `difficulty_band`, `access`,
`estimated_minutes`, `status`, `published_at`

### 3.3 `unit_topics` — ünite hangi konuları kapsıyor
`unit_id`, `topic_id`, `weight`
> Node'ların soru seçimi bunu miras alabilir (`inherit_from_unit`).

### 3.4 `exercises` — **havuz**, node'a değil topic'e bağlı
`topic_id` (zorunlu), `owner_course_id` (yalnızca yazım/sahiplik için), `owner_unit_id?`,
`type`, `content` (jsonb), `answer_key` (jsonb), `explanation`, `difficulty` (1–5),
`applicable_scopes` (jsonb: `["tyt","ayt"]`), `media`, `status`, `version`

> `applicable_scopes` sayesinde bir "Bileşke Fonksiyon" sorusu hem TYT hem AYT
> havuzuna girer; sadece AYT seviyesindeki bir soru `["ayt"]` ile işaretlenir.
> **Soru artık tek bir node'un malı değil** — bu, aşağıdaki her şeyi mümkün kılan karar.

### 3.5 `unit_nodes` — sabit soru listesi değil, **tarif**

| Kolon | |
|---|---|
| `unit_id`, `title`, `sort_order` | |
| `node_type` | `study`, `matching`, `mini_challenge`, `unit_challenge`, `quick_review`, `exam_sim` |
| `difficulty` | `kolay` … `sinav_provasi` |
| `selection_rule` | **jsonb — soruların nereden geleceğinin tarifi** |
| `unlock_rule` | jsonb (composite kural ağacı) |
| `exercise_count`, `time_limit_sec?`, `consumes_hearts`, `xp_reward`, `access` | |

```jsonc
// 1) Havuzdan kural ile — varsayılan
{ "mode": "pool", "count": 7,
  "filters": { "topics": "inherit_from_unit",
               "difficulty": {"min": 2, "max": 3},
               "scope": "tyt",
               "types": ["multiple_choice","ordering"],
               "exclude_seen_days": 14 },
  "distribution": { "by_topic": "even" },
  "fallback": "relax_difficulty" }

// 2) Elle seçilmiş sıra — öğretici sıralamanın önemli olduğu giriş node'ları
{ "mode": "fixed", "exercise_ids": [771, 772, 773] }

// 3) Kullanıcının yanlışları — "Hızlı Tekrar"
{ "mode": "review_queue", "count": 10, "scope": "unit" }

// 4) Zayıf konu ağırlıklı — Faz 2 adaptif
{ "mode": "adaptive", "count": 10, "weak_topic_ratio": 0.6 }

// 5) Sınav provası — blueprint'ten
{ "mode": "blueprint", "blueprint_id": 3 }
```

**Neden bu kadar önemli:** Mini Challenge, Ünite Challenge, Hızlı Tekrar, Sınav Provası
ve adaptif zorluk — hepsi **aynı motorun farklı kuralları**. Ayrı özellik, ayrı kod,
ayrı tablo değil. Yeni bir oyun modu istendiğinde çoğu zaman cevap yeni bir JSON kuralıdır.

### 3.6 `exam_blueprints` — deneme kompozisyonu ve net hesabı
`exam_blueprints`: `exam_section_id?`, `exam_variant_id?`, `name` ("TYT Genel Deneme"),
`duration_min` (165), `scoring_rule` (jsonb: `{"penalty_ratio": 0.25}`), `status`
`exam_blueprint_items`: `blueprint_id`, `course_id` \| `topic_id`, `question_count`,
`difficulty_distribution` (jsonb: `{"1":0.2,"2":0.3,"3":0.3,"4":0.15,"5":0.05}`)

```
TYT Genel Deneme · 120 soru · 165 dk · yanlış 1/4 götürür
  TYT Türkçe            40
  TYT Temel Matematik   40
  TYT Tarih 5 · Coğrafya 5 · Felsefe 5 · Din Kültürü 5
  TYT Fizik 7 · Kimya 7 · Biyoloji 6
```

> "Sonunda net / puan tahmini" ekranı tamamen buradan beslenir. LGS'nin farklı ceza
> oranı (3 yanlış = 1 doğru) sadece `scoring_rule` farkıdır — kod aynı.

---

## 4. Kod tarafı: dört strateji arayüzü

Esnekliğin bedeli genelde `if/switch` çorbasıdır. Onun yerine dört nokta, dört arayüz:

```php
// 1) Soru seçimi — selection_rule.mode'a göre çözülür
interface ExerciseSelector {
    public function mode(): SelectionMode;
    public function select(SelectionContext $ctx): ExerciseSet;
}
// PoolSelector · FixedListSelector · ReviewQueueSelector · AdaptiveSelector · BlueprintSelector

// 2) Yol kurgusu — aynı içerik, kullanıcıya göre farklı sıra
interface PathStrategy {
    public function assemble(CourseStructure $course, LearnerContext $learner): Path;
}
// SequentialPath (MVP) · GradeAwarePath · ExamCountdownPath · WeaknessFirstPath (Faz 2)

// 3) Kilit açma
interface UnlockRule { public function isSatisfiedBy(ProgressSnapshot $s): bool; }

// 4) Puanlama (net/puan)
interface ScoringRule { public function score(SessionResult $r): ExamScore; }
// StandardNetScoring(penalty: 0.25) · NoPenaltyScoring · WeightedScoring
```

`LearnerContext` = `{variant, field, grade, targetYear, topicMastery, entitlement, timezone}`.
Aynı course, 10. sınıf Sayısal öğrencisine ve mezun EA öğrencisine **farklı sırada** sunulur;
içerik tek, görünüm kullanıcıya özel.

Her arayüz container tag'i ile registry'ye kayıtlı. Yeni davranış = yeni sınıf + tag.
Mevcut hiçbir dosya açılmaz (OCP).

---

## 5. İçerik operasyonunu ayakta tutan iki şablon

21 course × ~8 ünite × ~6 node ≈ **1000 node**. Elle kurulursa içerik ekibi boğulur.

### `node_templates` / `unit_templates`
```jsonc
// "standart ünite" şablonu
{ "code": "standart_unite",
  "nodes": [
    {"title":"Çalışma 1","type":"study","difficulty":"kolay","count":6,"xp":10},
    {"title":"Çalışma 2","type":"study","difficulty":"kolay_orta","count":7,"xp":15},
    {"title":"Kavramları Eşleştir","type":"matching","difficulty":"orta","count":5,"xp":20},
    {"title":"Çalışma 3","type":"study","difficulty":"orta_zor","count":7,"xp":30},
    {"title":"Hızlı Tekrar","type":"quick_review","count":10,"xp":10},
    {"title":"Ünite Challenge","type":"unit_challenge","difficulty":"zor","count":10,"xp":100}
  ]}
```

Panelde: **Ünite oluştur → şablon seç → konuları işaretle → 6 node kural, XP ve kilitleriyle
hazır gelir.** İçerik ekibi sadece soru yazar. Editör istisna durumda tek tek düzenler.

Aynı şekilde `course_templates`: "TYT dersi" şablonu = ünite iskeleti + varsayılan node deseni.

---

## 6. Yayınlama: yapı bugün, içerik kademeli

TYT+AYT'nin tamamı **şemada** ilk günden var (21 course, 5 varyant, tüm eşlemeler seed'de).
Ama `status = published` olan içerik kademeli açılır. Yayın bir **veri kararıdır, deploy değil**.

| Aşama | Yayınlanan | Yaklaşık soru |
|---|---|---|
| Lansman | TYT Türkçe, TYT Matematik, TYT Tarih, TYT Coğrafya (3'er ünite) + **AYT Matematik (2 ünite)** | ~1.400 |
| +4 hafta | TYT Fizik, Kimya, Biyoloji | +900 |
| +8 hafta | AYT Fizik/Kimya/Biyoloji (SAY tamam), AYT Edebiyat | +1.200 |
| +12 hafta | AYT Tarih-1/2, Coğrafya-1/2, Felsefe Grubu (EA + SÖZ tamam) | +1.500 |

> **AYT Matematik'i lansmana koymamın sebebi test değil ispat:** AYT yolunun uçtan uca
> çalıştığını (alan filtresi, sekme, blueprint, paywall) gerçek kullanıcıyla doğrulamak,
> 11 AYT dersini yazdıktan sonra keşfetmekten çok ucuz.

Yayınlanmamış course, varyantın ders listesinde ya hiç görünmez ya da "Yakında" rozetiyle
görünür (`exam_variant_courses.placeholder_label`) — ikisi de config.

### Havuz yeterlilik kontrolü (yayın kapısı)
Bir node yayınlanmadan önce `SelectionRuleValidator` kuralı **kuru çalıştırır**:
"bu kural şu an 4 soru getiriyor, 7 gerekiyor" → yayın engellenir. Havuz modelinin
tek gerçek riski budur ve kapıda yakalanır.

---

## 7. Güncellenen ilerleme tabloları

| Tablo | Değişiklik |
|---|---|
| `user_enrollments` | `exam_track_id` → **`exam_variant_id`** + `is_primary`, `field_changed_at?` |
| `user_course_progress` | `user_subject_progress` yerine: `user_id`, `course_id`, `level`, `xp`, `completed_units`, `total_units` → "TYT Tarih · Lv 6 · 2/8 ünite · 650 XP" |
| `user_unit_progress` / `user_node_progress` | Değişmedi — `course_id` üzerinden bağlı, varyanttan bağımsız |
| `user_topic_stats` | **`topic_id` subject seviyesinde** → TYT/AYT birleşik ustalık |
| `session_items` | `content_snapshot` zaten vardı; havuz modeliyle artık **zorunlu** (kural her seferinde farklı soru getirebilir) |

**Tek cümlelik garanti:** İlerleme hiçbir yerde `exam_variant_id`'ye bağlı değildir.
Öğrenci alan değiştirdiğinde tek satır güncellenir, öğrendiği hiçbir şey kaybolmaz.

---

## 8. Örnek akış: Ege, 11. sınıf, Sayısal

1. Onboarding → `exam_variant = yks_say`, `grade = 11`, `target_year = 2027`
2. `GET /me/courses` → `exam_variant_courses` (13 satır) + `user_course_progress` join
   → `exam_section`'a göre gruplanır: **TYT sekmesi (9 ders) · AYT sekmesi (4 ders)**
3. `GET /courses/tyt_tarih/path` → `PathStrategy` (11. sınıf, 2027) üniteleri sıralar
4. Node'a girer → `selection_rule` → `PoolSelector` → ünitenin konularından 7 soru,
   son 14 günde görülenler hariç → oturum snapshot'ına yazılır
5. Cevaplar → `answer_attempts.topic_id` → `user_topic_stats` güncellenir
6. Aralık'ta EA'ya geçer → `user_enrollments` tek satır update → TYT'nin tamamı ve
   AYT Matematik ilerlemesi yerinde; listeye AYT Edebiyat, Tarih-1, Coğrafya-1 eklenir

---

## 9. Nerede durduğumuz — bilinçli olarak yapmadıklarımız

Esneklik sonsuza kadar götürülebilir; götürmüyoruz:

- **Ünite ↔ course çoka-çok değil.** Ünite tek bir course'a aittir. Paylaşım *soru
  havuzu* seviyesinde (topic üzerinden) yapılır. Çoka-çok ilerleme semantiğini
  ("bu üniteyi TYT'de bitirdim, AYT'de de bitti mi?") çözülemez hale getirirdi.
- **Kullanıcı tanımlı müfredat yok.** Öğrenci kendi ünitesini kuramaz. Faz 3'te
  "kendi tekrar listen" gelebilir, o da `review_queue` üzerinden.
- **Kural motoru bir DSL değil.** `selection_rule`/`unlock_rule` sabit şemalı JSON'dur,
  ifade dili değil. Panelde form ile üretilir, elle yazılmaz.
- **Müfredat yıl sürümlemesi MVP'de yok.** `units.grade_level` +
  `courses.effective_from_year` alanları şemada duruyor ama tek sürüm yayınlanıyor.
  ÖSYM müfredat değiştirdiğinde açılır.
