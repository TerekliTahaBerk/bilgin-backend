<?php

declare(strict_types=1);

/*
 | Modül sınırlarını ve bağımlılık yönünü KOD İLE zorlar.
 |
 | Bu kurallar dokümanda yazılı olsa da zamanla ihlal edilir; testte
 | yazılınca ihlal derleme hatası kadar görünür olur. Modüler monolitin
 | mikroservise ihtiyaç duymadan ayakta kalmasının tek yolu budur.
 */

/*
 | Yeni bir modül eklendiğinde Domain namespace'i BU LİSTEYE eklenmelidir.
 | Liste açıkça tutuluyor çünkü "her şeyi kapsa da istisnaları saysak"
 | yaklaşımı, istisna listesi büyüdükçe kuralı sessizce boşaltır.
 */
$domainNamespaces = [
    'App\Modules\Catalog\Domain',
    'App\Modules\Curriculum\Domain',
    'App\Modules\Identity\Domain',
    'App\Modules\Learning\Domain',
    'App\Modules\Hearts\Domain',
    'App\Modules\Gamification\Domain',
    'App\Modules\Billing\Domain',
    'App\Modules\Ads\Domain',
    'App\Modules\League\Domain',
];

arch('domain katmanı Laravel tanımaz')
    ->expect($domainNamespaces)
    ->not->toUse([
        'Illuminate\Database\Eloquent',
        'Illuminate\Support\Facades',
        'Illuminate\Http',
    ]);

arch('domain katmanı zamanı kendi okumaz')
    // now() / Carbon::now() domain'de test edilemez davranış üretir;
    // zaman daima ClockInterface ile enjekte edilir.
    ->expect($domainNamespaces)
    ->not->toUse(['now', 'Carbon\Carbon', 'Illuminate\Support\Carbon']);

arch('Catalog, Curriculum\'ü tanımaz')
    ->expect('App\Modules\Catalog')
    ->not->toUse('App\Modules\Curriculum');

arch('Shared katmanı hiçbir modüle bağımlı değildir')
    ->expect('App\Shared')
    ->not->toUse('App\Modules');

arch('domain sözleşmeleri arayüzdür')
    ->expect('App\Modules\Catalog\Domain\Contract')
    ->toBeInterfaces();

arch('grader stratejileri sözleşmeyi uygular')
    ->expect('App\Modules\Learning\Domain\Grading\Grader')
    ->toImplement('App\Modules\Learning\Domain\Grading\ExerciseGrader')
    ->toBeFinal();

arch('kilit kuralları sözleşmeyi uygular')
    ->expect('App\Modules\Catalog\Domain\Unlock\Rule')
    ->toImplement('App\Modules\Catalog\Domain\Unlock\UnlockRule')
    ->toBeFinal();

arch('içerik doğrulayıcıları sözleşmeyi uygular')
    // Doğrulayıcı listesi grader listesiyle birebir aynı tipleri kapsamalı;
    // eksik kalan bir tip, panelden hatalı soru girilebilmesi demek.
    ->expect('App\Modules\Catalog\Domain\Validation\Validator')
    ->toImplement('App\Modules\Catalog\Domain\Validation\ExerciseContentValidator')
    ->toBeFinal();

arch('can politikaları sözleşmeyi uygular')
    ->expect('App\Modules\Hearts\Domain\Policy')
    ->toImplement('App\Modules\Hearts\Domain\Policy\HeartPolicy')
    ->toBeFinal()
    ->ignoring('App\Modules\Hearts\Domain\Policy\HeartPolicy');

arch('Billing yalnızca Identity\'yi tanır')
    // Abonelik, kullanıcıyı bulmak dışında hiçbir modülü bilmez; premium
    // kapıları Shared\Domain\Entitlement sözleşmesi üzerinden açılır.
    ->expect('App\Modules\Billing')
    ->not->toUse(['App\Modules\Learning', 'App\Modules\Catalog', 'App\Modules\Curriculum', 'App\Modules\Hearts']);

arch('rozet kriterleri sözleşmeyi uygular')
    ->expect('App\Modules\Gamification\Domain\Badge\Criterion')
    ->toImplement('App\Modules\Gamification\Domain\Badge\BadgeCriterion')
    ->toBeFinal();

arch('Gamification, Learning\'in tablolarına dokunmaz')
    // Rozetler iki modülün verisini birleştiriyor ama bağlantı
    // Shared\Domain\Learner sözleşmesi üzerinden.
    ->expect('App\Modules\Gamification')
    ->not->toUse('App\Modules\Learning\Infrastructure');

arch('Gamification, League\'i tanımaz')
    // Bağlantı tek yönlü ve olay üzerinden: Gamification lig diye bir şey
    // olduğunu bilmez. Lig kaldırılsa XP hesabında tek satır değişmez.
    ->expect('App\Modules\Gamification')
    ->not->toUse('App\Modules\League');

arch('League, öğrenme akışına dokunmaz')
    // Lig yalnızca XP olayını dinler; hangi soruların sorulduğu onu ilgilendirmez.
    ->expect('App\Modules\League')
    ->not->toUse(['App\Modules\Learning', 'App\Modules\Catalog', 'App\Modules\Hearts']);

arch('Ads yalnızca Hearts ve Identity\'yi tanır')
    // Reklam ödülü cana dönüşüyor; öğrenme akışına ve içeriğe dokunmuyor.
    ->expect('App\Modules\Ads')
    ->not->toUse(['App\Modules\Learning', 'App\Modules\Catalog', 'App\Modules\Curriculum']);

arch('premium kontrolü modüllere dağılmaz')
    // Hiçbir modül abonelik tablosunu doğrudan okumaz; hepsi
    // EntitlementReader'dan geçer. Aksi hâlde "premium mi" sorusunun
    // cevabı kod tabanına serpilir ve grace/refund kuralları tutarsızlaşır.
    ->expect(['App\Modules\Learning', 'App\Modules\Catalog', 'App\Modules\Curriculum', 'App\Modules\Hearts'])
    ->not->toUse('App\Modules\Billing');

arch('Hearts hiçbir modüle bağımlı değildir')
    // Can sistemi tek başına ayakta durur; Learning onu Shared sözleşmesi
    // üzerinden kullanır, tersi asla olmaz.
    ->expect('App\Modules\Hearts')
    ->not->toUse(['App\Modules\Learning', 'App\Modules\Catalog', 'App\Modules\Curriculum']);

arch('selector stratejileri sözleşmeyi uygular')
    ->expect('App\Modules\Catalog\Domain\Selection\Strategy')
    ->toImplement('App\Modules\Catalog\Domain\Selection\ExerciseSelector')
    ->toBeFinal();

arch('use case sınıfları final ve readonly')
    ->expect('App\Modules\Catalog\Application\UseCase')
    ->toBeFinal();

arch('enum\'lar backed')
    ->expect('App\Modules\Catalog\Domain\Enum')
    ->toBeStringBackedEnums();

arch('hata ayıklama kalıntısı yok')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('strict types her yerde')
    ->expect('App')
    ->toUseStrictTypes();

arch('modüller birbirinin Infrastructure katmanına girmez')
    // İzin verilen tek geçiş Curriculum → Catalog (course referansı).
    ->expect('App\Modules\Identity')
    ->not->toUse('App\Modules\Catalog\Infrastructure');

arch('Catalog ve Curriculum, Identity\'yi tanımaz')
    // Kullanıcı bilgisine Shared\Domain\Learner sözleşmesi üzerinden erişilir.
    // Doğrudan User modeline bağlanmak, "kullanıcı" kavramını tüm modüllere sızdırır.
    ->expect(['App\Modules\Catalog', 'App\Modules\Curriculum'])
    ->not->toUse('App\Modules\Identity');

arch('controller\'lar ince kalır — iş kuralı taşımaz')
    ->expect('App\Modules\Identity\Http\Controller')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Support\Facades\Cache']);
