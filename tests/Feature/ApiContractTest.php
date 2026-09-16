<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Osteel\OpenApi\Testing\ValidatorBuilder;
use Symfony\Component\Yaml\Yaml;

/*
 | SÖZLEŞME TESTİ — docs/openapi.yaml ile kodun ayrışmasını engeller.
 |
 | Flutter ve Next.js ekipleri bu spec'e göre paralel çalışıyor. Spec elle
 | yazıldığı için tek başına bir vaattir; bu test o vaadi zorunluluğa çevirir:
 |   1. her gerçek yanıt spec'teki şemaya karşı doğrulanır,
 |   2. spec'te olmayan bir uç eklenirse test kırılır.
 |
 | İkincisi daha önemli: belgesiz bir uç, istemci ekibinin haberi olmadan
 | eklenen bir uçtur.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->validator = ValidatorBuilder::fromYamlFile(base_path('docs/openapi.yaml'))->getValidator();

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'sozlesme-testi',
        'platform' => 'ios',
    ])->json('data.token');
});

/** Yanıtı spec'e karşı doğrular; uymazsa testi kırar. */
function assertMatchesSpec(TestResponse $response, string $path, string $method): void
{
    expect(test()->validator->validate($response->baseResponse, $path, $method))->toBeTrue();
}

it('misafir giriş yanıtı sözleşmeye uyar', function (): void {
    $response = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'yeni-cihaz',
        'platform' => 'android',
        'app_version' => '1.2.0',
    ])->assertCreated();

    assertMatchesSpec($response, '/v1/auth/guest', 'post');
});

it('onboarding yanıtı sözleşmeye uyar', function (): void {
    $response = $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks',
        'field' => 'say',
        'grade' => '11',
        'target_exam_year' => 2027,
        'name' => 'Ege',
        'daily_goal_rounds' => 3,
        'reminder_time' => '20:00',
    ])->assertCreated();

    assertMatchesSpec($response, '/v1/onboarding', 'post');
});

it('me yanıtı sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    assertMatchesSpec($this->withToken($this->token)->getJson('/api/v1/me')->assertOk(), '/v1/me', 'get');
});

it('onboarding yapılmamış kullanıcının profili de sözleşmeye uyar', function (): void {
    // enrollment ve profile null olabilir — sözleşme bunu kapsamalı.
    assertMatchesSpec($this->withToken($this->token)->getJson('/api/v1/me')->assertOk(), '/v1/me', 'get');
});

it('ders listesi yanıtı sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/me/courses')->assertOk(),
        '/v1/me/courses',
        'get',
    );
});

it('onboarding yapılmadan alınan hata da sözleşmeye uyar', function (): void {
    // Hata zarfı da sözleşmenin parçası; istemci buna göre dallanıyor.
    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/me/courses')->assertStatus(409),
        '/v1/me/courses',
        'get',
    );
});

it('alan değiştirme yanıtı sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    assertMatchesSpec(
        $this->withToken($this->token)->patchJson('/api/v1/me/enrollment', ['field' => 'ea'])->assertOk(),
        '/v1/me/enrollment',
        'patch',
    );
});

it('ünite yolu yanıtı sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'grade' => '11',
    ]);

    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();

    assertMatchesSpec(
        $this->withToken($this->token)->getJson("/api/v1/courses/{$course->id}/path")->assertOk(),
        '/v1/courses/{course}/path',
        'get',
    );
});

it('yayında olmayan ders hatası sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);
    $course = Course::query()->where('code', 'tyt_fizik')->firstOrFail();

    assertMatchesSpec(
        $this->withToken($this->token)->getJson("/api/v1/courses/{$course->id}/path")->assertNotFound(),
        '/v1/courses/{course}/path',
        'get',
    );
});

it('oturum akışının üç yanıtı da sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $node = UnitNode::query()->whereIn('unit_id', $course->units()->pluck('id'))->orderBy('sort_order')->firstOrFail();

    $start = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->assertCreated();

    assertMatchesSpec($start, '/v1/sessions', 'post');

    $sessionId = $start->json('data.session_id');
    $items = StudySession::query()->where('uuid', $sessionId)->firstOrFail()->items()->get();

    // Biri yanlış (can düşer, hearts alanı dolar), gerisi doğru.
    $wrong = $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/answers", [
        'exercise_id' => $items[0]->exercise_id,
        'answer' => ['option_id' => 'gecersiz', 'value' => 'gecersiz'],
        'elapsed_ms' => 5000,
    ])->assertOk();

    assertMatchesSpec($wrong, '/v1/sessions/{session}/answers', 'post');

    foreach ($items->skip(1) as $item) {
        $key = $item->answer_key_snapshot;

        $answer = match ($item->type->value) {
            'multiple_choice' => ['option_id' => $key['correct_option_id']],
            'true_false' => ['value' => $key['value']],
            'fill_blank' => ['blanks' => $key['blanks']],
            'matching' => ['pairs' => $key['pairs']],
            'ordering', 'word_order' => ['order' => $key['order']],
            'numeric_input' => ['value' => $key['value']],
            default => ['known' => true],
        };

        $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/answers", [
            'exercise_id' => $item->exercise_id,
            'answer' => $answer,
            'elapsed_ms' => 5000,
        ])->assertOk();
    }

    assertMatchesSpec(
        $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/complete")->assertOk(),
        '/v1/sessions/{session}/complete',
        'post',
    );
});

it('turu bırakma yanıtı sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);
    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $node = UnitNode::query()->whereIn('unit_id', $course->units()->pluck('id'))->orderBy('sort_order')->firstOrFail();

    $sessionId = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->json('data.session_id');

    assertMatchesSpec(
        $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/abandon")->assertOk(),
        '/v1/sessions/{session}/abandon',
        'post',
    );
});

it('can bakiyesi yanıtı sözleşmeye uyar', function (): void {
    assertMatchesSpec($this->withToken($this->token)->getJson('/api/v1/hearts')->assertOk(), '/v1/hearts', 'get');
});

it('çıkış yanıtı sözleşmeye uyar', function (): void {
    assertMatchesSpec(
        $this->withToken($this->token)->postJson('/api/v1/auth/logout')->assertOk(),
        '/v1/auth/logout',
        'post',
    );
});

it('belgesiz istemci ucu yoktur', function (): void {
    // Spec'te olmayan bir uç, istemci ekibinin haberi olmadan eklenmiş
    // demektir. Bu testin varlık sebebi tam olarak bunu imkânsız kılmak.
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));
    $documented = array_keys($spec['paths']);

    $actual = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->map(fn ($route): string => '/'.str_replace('api/', '', $route->uri()))
        ->unique()
        ->values();

    $undocumented = $actual->reject(fn (string $uri): bool => in_array($uri, $documented, true));

    expect($undocumented->all())->toBe([], 'Belgesiz uç(lar): '.$undocumented->implode(', '));
});

it('belgelenmiş her uç gerçekten vardır', function (): void {
    // Ters yön: spec'te olup kodda olmayan uç, istemcinin boşa
    // implemente edeceği bir vaat demektir.
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    $actual = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->map(fn ($route): string => '/'.str_replace('api/', '', $route->uri()))
        ->unique();

    $missing = collect(array_keys($spec['paths']))->reject(fn (string $uri): bool => $actual->contains($uri));

    expect($missing->all())->toBe([], 'Kodda olmayan belgelenmiş uç(lar): '.$missing->implode(', '));
});

it('premium hakları yanıtı sözleşmeye uyar', function (): void {
    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/me/entitlements')->assertOk(),
        '/v1/me/entitlements',
        'get',
    );
});

it('paywall yanıtı sözleşmeye uyar', function (): void {
    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/premium/offerings')->assertOk(),
        '/v1/premium/offerings',
        'get',
    );
});

it('webhook yanıtı sözleşmeye uyar', function (): void {
    config(['tekrarla.billing.revenuecat.webhook_secret' => 'test-secret']);

    $response = $this->postJson('/api/v1/webhooks/revenuecat', [
        'event' => ['id' => 'evt_1', 'type' => 'SUBSCRIBER_ALIAS', 'app_user_id' => 'x'],
    ], ['Authorization' => 'test-secret'])->assertOk();

    assertMatchesSpec($response, '/v1/webhooks/revenuecat', 'post');
});

it('deneme listesi ve başlatma yanıtları sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/exam-simulations')->assertOk(),
        '/v1/exam-simulations',
        'get',
    );

    $blueprintId = DB::table('exam_blueprints')
        ->where('code', 'tyt_genel_deneme')->value('id');

    assertMatchesSpec(
        $this->withToken($this->token)
            ->postJson('/api/v1/exam-simulations', ['blueprint_id' => $blueprintId])
            ->assertCreated(),
        '/v1/exam-simulations',
        'post',
    );
});

it('deneme sonuç yanıtı sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    $blueprintId = DB::table('exam_blueprints')
        ->where('code', 'tyt_genel_deneme')->value('id');

    $sessionId = $this->withToken($this->token)
        ->postJson('/api/v1/exam-simulations', ['blueprint_id' => $blueprintId])
        ->json('data.session_id');

    assertMatchesSpec(
        $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/complete")->assertOk(),
        '/v1/sessions/{session}/complete',
        'post',
    );
});

it('sosyal giriş çakışma yanıtı sözleşmeye uyar', function (): void {
    // Sağlayıcı yapılandırılmamışken doğrulama başarısız olur; hata zarfının
    // kendisi de sözleşmenin parçası.
    assertMatchesSpec(
        $this->postJson('/api/v1/auth/social', [
            'provider' => 'apple',
            'identity_token' => 'gecersiz.token.degeri',
        ])->assertUnauthorized(),
        '/v1/auth/social',
        'post',
    );
});

it('reklam politikası yanıtı sözleşmeye uyar', function (): void {
    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/ads/policy')->assertOk(),
        '/v1/ads/policy',
        'get',
    );
});

it('reklam doğrulama hatası sözleşmeye uyar', function (): void {
    assertMatchesSpec(
        $this->getJson('/api/v1/webhooks/admob/ssv?transaction_id=x&user_id=y&signature=z&key_id=1')
            ->assertUnauthorized(),
        '/v1/webhooks/admob/ssv',
        'get',
    );
});

it('lig yanıtları sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    // Henüz XP kazanılmadığı için "katılmadı" hâli.
    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/league/current')->assertOk(),
        '/v1/league/current',
        'get',
    );

    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/league/history')->assertOk(),
        '/v1/league/history',
        'get',
    );
});

it('profil yanıtları sözleşmeye uyar', function (): void {
    $this->withToken($this->token)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/me/stats')->assertOk(),
        '/v1/me/stats',
        'get',
    );

    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/me/badges')->assertOk(),
        '/v1/me/badges',
        'get',
    );

    assertMatchesSpec(
        $this->withToken($this->token)->getJson('/api/v1/me/topics')->assertOk(),
        '/v1/me/topics',
        'get',
    );
});
