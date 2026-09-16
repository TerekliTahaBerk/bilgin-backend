# 01 · Backend Mimarisi

Laravel 12 · PHP 8.4 · PostgreSQL 16 · Redis 7 · Horizon (queue) · Sanctum (auth)

---

## 1. Temel karar: Modüler Monolit

Mikroservis **yok**. Tek deploy edilebilir Laravel uygulaması, içinde net sınırları olan
modüller (bounded context). Sebep: ekip küçük, trafik tek bölgede, asıl risk içerik
operasyonu — dağıtık sistem karmaşıklığı burada sadece maliyet üretir.

Ama monolit ≠ çorba. Kural şu:

> Bir modül başka bir modülün Eloquent modeline, tablosuna veya iç sınıfına **dokunamaz**.
> Modüller arası iletişim yalnızca (a) yayımlanmış `Application` arayüzleri ve (b) domain event'leri üzerinden olur.

Bu kural, ileride "Lig" veya "Billing"i ayırmak gerekirse çıkarmayı ucuzlaştırır.

### Modüller

| Modül | Sorumluluk | Sahip olduğu tablolar |
|---|---|---|
| `Identity` | Kullanıcı, auth, onboarding profili, cihaz/push token | `users`, `user_profiles`, `user_enrollments`, `devices` |
| `Curriculum` | Sınav yapısı: *öğrencinin neye ihtiyacı var* — oturum, alan/varyant, ders eşlemesi, deneme kompozisyonu | `exams`, `exam_sections`, `exam_variants`, `exam_variant_courses`, `exam_blueprints`, `exam_blueprint_items` |
| `Catalog` | İçerik: *elimizde ne var* — ders müfredatı, ünite, node tarifi, soru havuzu, yayınlama | `subjects`, `topics`, `courses`, `units`, `unit_topics`, `unit_nodes`, `exercises`, `unit_templates`, `content_releases` |
| `Learning` | Çalışma oturumu, cevap değerlendirme, ilerleme, kilit açma, tekrar kuyruğu | `study_sessions`, `session_items`, `answer_attempts`, `user_*_progress`, `review_queue` |
| `Gamification` | XP defteri, level, seri, rozet, günlük görev | `xp_ledger`, `user_stats`, `streak_freezes`, `badges`, `user_badges`, `daily_quests` |
| `Hearts` | Can bakiyesi, rejenerasyon, harcama/kazanma | `user_hearts`, `heart_transactions` |
| `League` | Haftalık lig kohortları, sıralama, terfi/düşme | `leagues`, `league_memberships` |
| `Billing` | Abonelik, entitlement, RevenueCat webhook | `subscriptions`, `subscription_events` |
| `Ads` | Ödüllü reklam doğrulama (SSV), günlük limit | `ad_rewards` |
| `Analytics` | Olay toplama ve dışa aktarım (Amplitude/Mixpanel) | `analytics_events` (buffer) |
| `Admin` | Panel API'si, rol/yetki, denetim kaydı | `admin_users`, `audit_logs` |

Bağımlılık yönü tek yönlü olmalı; `Catalog` kimseyi tanımaz, `Curriculum` yalnızca
`Catalog`'a *referans verir* (course id), `Learning` ikisini de okur,
`Gamification`/`Hearts`/`League` yalnızca event dinler.

```
Identity ────┐
Curriculum ──┤
Catalog ─────┼──> Learning ──(event)──> Gamification ──(event)──> League
Billing ─────┘                     └──> Hearts             └────> Analytics
Ads ─────────────────────────────> Hearts
```

> `Curriculum` / `Catalog` ayrımı bu projedeki en önemli sınır. Ayrıntı ve gerekçe:
> [`06-ders-ve-mufredat-sistemi.md`](06-ders-ve-mufredat-sistemi.md).

---

## 2. Klasör yapısı

```
tekrarla_backend/
├── app/
│   ├── Modules/
│   │   └── Learning/
│   │       ├── Domain/
│   │       │   ├── Enum/            ExerciseType, NodeType, DifficultyLevel
│   │       │   ├── ValueObject/     GradingResult, SessionScore, Accuracy
│   │       │   ├── Grading/         ExerciseGrader (interface) + 10 strateji
│   │       │   ├── Unlock/          UnlockRule (interface) + kural sınıfları
│   │       │   └── Contract/        StudySessionRepository, ProgressRepository
│   │       ├── Application/
│   │       │   ├── UseCase/         StartStudySession, SubmitAnswer, CompleteSession
│   │       │   ├── DTO/             StartSessionCommand, SubmitAnswerCommand ...
│   │       │   └── Event/           StudySessionCompleted, AnswerGraded
│   │       ├── Infrastructure/
│   │       │   ├── Eloquent/        Model/ + Repository/
│   │       │   └── Provider/        LearningServiceProvider
│   │       ├── Http/
│   │       │   ├── Controller/Api/V1/
│   │       │   ├── Request/         FormRequest'ler
│   │       │   └── Resource/        JsonResource'lar
│   │       ├── Database/            migrations/ factories/ seeders/
│   │       ├── Routes/              api.php
│   │       └── Tests/               Unit/ Feature/
│   ├── Shared/
│   │   ├── Clock/                   ClockInterface + SystemClock + FrozenClock (test)
│   │   ├── Idempotency/             IdempotencyKey, IdempotentExecutor
│   │   ├── Result/                  Result<T, DomainError>
│   │   ├── Http/                    ApiController, ApiResponse, ExceptionHandler
│   │   └── Domain/                  DomainEvent, AggregateId, Money
│   └── Providers/
├── config/tekrarla/                 hearts.php, xp.php, league.php, content.php
├── database/migrations/             (modüllerden autoload edilir)
├── docs/
└── tests/Architecture/              bağımlılık yönü testleri (Pest arch)
```

Modüller `composer.json` içinde PSR-4 ile `App\Modules\` altında autoload edilir; her
modülün kendi `ServiceProvider`'ı migration/route/binding'lerini kaydeder.

---

## 3. SOLID'in somut karşılıkları

Soyut prensip listesi değil — bu projede nerede, hangi sınıfta karşılığı var:

### S — Single Responsibility: "Action/UseCase" sınıfları

Fat service ve fat controller yok. Her iş akışı tek bir `__invoke`'lu sınıf:

```php
final readonly class SubmitAnswer
{
    public function __construct(
        private StudySessionRepository $sessions,
        private GraderRegistry $graders,
        private HeartService $hearts,          // Hearts modülünün public arayüzü
        private EventDispatcher $events,
        private ClockInterface $clock,
    ) {}

    public function __invoke(SubmitAnswerCommand $cmd): AnswerOutcome
    {
        $session = $this->sessions->findActiveOrFail($cmd->sessionId, $cmd->userId);
        $item    = $session->item($cmd->exerciseId);

        $result = $this->graders->for($item->type())->grade($item->content(), $cmd->answer);

        $session->record($item, $result, $cmd->elapsedMs, $this->clock->now());
        $this->sessions->save($session);

        if ($result->isWrong() && $session->consumesHearts()) {
            $this->hearts->consume($cmd->userId, reason: HeartReason::WrongAnswer);
        }

        $this->events->dispatch(new AnswerGraded(...));

        return AnswerOutcome::from($result, $this->hearts->balance($cmd->userId));
    }
}
```

Controller yalnızca: request → command DTO → use case → resource. 10 satırı geçmez.

### O — Open/Closed: strateji + registry (en kritik üç yer)

**1. Egzersiz değerlendirme.** Yeni egzersiz tipi eklemek hiçbir mevcut sınıfı
değiştirmemeli — UI'da 8+ tip var (çoktan seçmeli, boşluk doldurma, eşleştirme,
kronolojik sıralama, flashcard, doğru/yanlış, sürükle-bırak cümle, sayısal giriş).

```php
interface ExerciseGrader
{
    public function type(): ExerciseType;
    public function grade(ExerciseContent $content, SubmittedAnswer $answer): GradingResult;
}
```

Container tag'i ile kayıt (`$app->tag([...], 'exercise.grader')`), `GraderRegistry::for()`
tipe göre çözer. Yeni tip = 1 grader + 1 içerik doğrulayıcı (`ExerciseContentValidator`)
+ 1 JSON şema. Switch/case hiçbir yerde yok.

**2. Kilit açma kuralları.** `unit_nodes.unlock_rule` JSON olarak saklanır, `UnlockRuleFactory`
kural ağacına dönüştürür — composite pattern:

```php
interface UnlockRule { public function isSatisfiedBy(ProgressSnapshot $s): bool; }

// AllOf, AnyOf, PreviousNodeCompleted, MinAccuracy(80), RequiresPremium,
// SubjectLevelAtLeast, UnitCompleted
```

İçerik ekibi panelden kural kurgular, kod deploy'u gerekmez.

**3. Soru seçimi.** Node'lar sabit soru listesi değil *tarif* tutar
(`selection_rule` jsonb). `ExerciseSelector` implementasyonları: `PoolSelector`,
`FixedListSelector`, `ReviewQueueSelector`, `AdaptiveSelector`, `BlueprintSelector`.
Mini Challenge, Hızlı Tekrar, Sınav Provası ve adaptif zorluk — hepsi aynı motorun
farklı kuralı. Yeni oyun modu çoğu zaman yeni bir JSON kuralı, yeni kod değil.

**4. Yol kurgusu.** `PathStrategy` — aynı ders içeriği, kullanıcının sınıfına ve hedef
yılına göre farklı sırada sunulur (`SequentialPath` → `GradeAwarePath` → `WeaknessFirstPath`).

**5. XP hesabı.** Dekoratör zinciri: `BaseDifficultyXp` → `PerfectBonus` →
`ChallengeMultiplier` → `FirstCompletionBonus`. Yeni bonus = zincire yeni halka.

### L — Liskov: tüm grader'lar aynı sözleşmeyi bozmadan uygular

`GradingResult` her zaman `isCorrect`, `partialScore (0..1)`, `feedback`, `correctAnswer`
döner. Eşleştirme gibi kısmi puanlı tipler `partialScore` kullanır; çağıran taraf tipe
göre dallanmaz. Hiçbir grader "ben desteklemiyorum" diye exception atmaz — registry zaten
desteklenmeyeni çözmez.

Aynı şekilde `HeartPolicy`: `FreeHeartPolicy` ve `UnlimitedHeartPolicy` aynı arayüzü
uygular; premium kullanıcıda `consume()` no-op'tur, çağıran yerde `if (premium)` yoktur.

### I — Interface Segregation: ince arayüzler

`GameService` gibi tanrı sınıf yok. `Hearts` modülü dışarıya iki küçük arayüz açar:

```php
interface HeartBalanceReader { public function balance(UserId $u): HeartBalance; }
interface HeartConsumer      { public function consume(UserId $u, HeartReason $r): HeartBalance; }
```

`Learning` yalnızca `HeartConsumer`'a bağımlı; profil ekranı yalnızca `HeartBalanceReader`'a.

### D — Dependency Inversion: domain arayüzü, infra implementasyonu

`Domain/Contract/StudySessionRepository` arayüzü domain'de; `Infrastructure/Eloquent/EloquentStudySessionRepository`
implementasyonu infra'da; bağlama modülün ServiceProvider'ında. Aynısı `SubscriptionGateway`
(RevenueCat), `PushSender` (FCM), `AdVerifier` (AdMob SSV), `ClockInterface` için geçerli.

Faydası somut: lig hesabını Redis'ten Postgres'e almak, RevenueCat'ten Adapty'ye geçmek
tek sınıf değişimi.

---

## 4. Pragmatizm sınırı (önemli)

Tam hexagonal + ORM'siz saf domain **yapmıyoruz**. Laravel'de bu, iki kat maliyet ve
sıfır fayda üretir. Kural:

- **Saf domain sınıfı** (Eloquent tanımaz): grading, unlock, XP, can, seri, lig sıralaması.
  Bunlar kural yoğun, veritabanısız test edilebilir olmalı.
- **Eloquent doğrudan**: CRUD ağırlıklı yerler — catalog okuma, admin paneli, listeleme.
  Repository arayüzü yalnızca yukarıdaki kural yoğun akışların ihtiyacı olduğu yerde.

Aşırı soyutlama da clean code ihlalidir; ölçüt "bu arayüzün ikinci bir implementasyonu
gerçekten olacak mı / testte gerekiyor mu" sorusudur.

---

## 5. Güvenlik ve hile önleme (mimari seviyede)

Oyunlaştırılmış bir üründe **doğru cevap istemciye asla önceden gitmez**.

- `GET /sessions` yanıtı egzersizleri `answer` alanı olmadan döner; doğrulama sunucuda.
- Her cevap ayrı istekle gönderilir; sunucu `elapsed_ms` ve sunucu tarafı zaman damgasını
  karşılaştırır (insan-dışı hız → `suspicious` işareti, XP verilmez, log'lanır).
- XP, can, seri, lig puanı istemciden **hiç** alınmaz; yalnızca sunucu üretir.
- Premium durumu istemci iddiasıyla değil, `Billing` entitlement kaydıyla belirlenir.
- Ödüllü reklam ödülü AdMob **server-side verification** callback'i ile verilir;
  "reklamı izledim" diyen client isteği tek başına yeterli değildir.
- Tüm mutasyon endpoint'leri `Idempotency-Key` alır (offline/retry senaryosu) —
  aynı anahtar ikinci kez XP/can üretmez.

---

## 6. Test stratejisi

| Katman | Araç | Kapsam |
|---|---|---|
| Domain birim testi | Pest, DB yok | Grader'lar (her tip için doğru/yanlış/kısmi), unlock kuralları, XP zinciri, can rejenerasyonu (FrozenClock), seri kırılması, lig terfi hesabı |
| Use case testi | Pest + SQLite/Postgres | StartSession → SubmitAnswer → Complete akışı, idempotency, can bitince 409 |
| API sözleşme testi | Pest Feature | Her endpoint için şema ve hata kodu doğrulaması |
| Mimari testi | Pest Arch / Deptrac | `Domain` → `Illuminate` bağımlılığı yasak; modüller arası çapraz `Infrastructure` erişimi yasak |
| Yük testi | k6 | Oturum akışı 500 rps hedefi, lig kapanış job'ı 100k kullanıcı |

Hedef: domain katmanında satır kapsamı ≥ %90, genelde ≥ %70. CI'da `pint` (stil),
`phpstan level 8`, `pest` zorunlu.

---

## 7. Altyapı ve operasyon

- **Kuyruk**: Redis + Horizon. Ayrı kuyruklar: `default`, `gamification` (XP/rozet),
  `notifications` (push), `league` (haftalık kapanış), `analytics`.
- **Zamanlanmış işler**: can rejenerasyonu *cron ile değil*, okuma anında hesaplanır
  (bkz. 04). Cron yalnızca: lig kapanışı (Pzt 00:00 TRT), seri hatırlatma push'u
  (kullanıcının seçtiği saatte, 17:00/20:00/22:00), günlük görev üretimi, analytics flush.
- **Cache**: içerik yanıtları ETag + Redis; kullanıcı entitlement'ı 5 dk cache.
- **Medya**: Cloudflare R2 (görsel, YDS ses dosyaları), imzalı URL.
- **Hata izleme**: Sentry. **Log**: JSON structured, request-id korelasyonu.
- **Ortamlar**: local (Sail) → staging → production. Deploy: GitHub Actions → zero-downtime.
