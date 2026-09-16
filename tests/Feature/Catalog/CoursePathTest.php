<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use Database\Seeders\DatabaseSeeder;

/*
 | Ünite yolu — tasarımın imza ekranı. Sıralama, kilit ve ilerleme
 | üç ayrı kaynaktan geliyor; bu test üçünün birleşimini koruyor.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'yol-testi',
        'platform' => 'ios',
    ])->json('data.token');

    $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks',
        'field' => 'say',
        'grade' => '11',
        'target_exam_year' => 2027,
    ]);
});

function pathFor(string $courseCode): array
{
    $course = Course::query()->where('code', $courseCode)->firstOrFail();

    return test()->withToken(test()->token)
        ->getJson("/api/v1/courses/{$course->id}/path")
        ->assertOk()
        ->json('data');
}

it('ünite yolunu node\'larıyla döner', function (): void {
    $data = pathFor('tyt_tarih');

    expect($data['course']['code'])->toBe('tyt_tarih')
        ->and($data['units'])->toHaveCount(1)
        ->and($data['units'][0]['nodes'])->toHaveCount(6)
        ->and($data['units'][0]['title'])->toBe('İlk ve Orta Çağlarda Türk Dünyası');
});

it('yeni kullanıcıda yalnızca ilk node açıktır', function (): void {
    $nodes = pathFor('tyt_tarih')['units'][0]['nodes'];

    expect($nodes[0]['state'])->toBe('available')
        ->and($nodes[1]['state'])->toBe('locked')
        ->and($nodes[1]['lock_reason'])->toBe('PREVIOUS_NODE_INCOMPLETE');
});

it('ünite challenge farklı sebeple kilitlidir', function (): void {
    // "Önceki node bitmedi" değil, "ünite bitmedi" — kullanıcıya
    // gösterilecek mesajın doğrusu bu.
    $nodes = collect(pathFor('tyt_tarih')['units'][0]['nodes']);
    $challenge = $nodes->firstWhere('type', 'unit_challenge');

    expect($challenge['state'])->toBe('locked')
        ->and($challenge['lock_reason'])->toBe('UNIT_INCOMPLETE')
        ->and($challenge['time_limit_sec'])->toBe(600);
});

it('sınıfı bilinen öğrenciye grade_aware strateji uygular', function (): void {
    expect(pathFor('tyt_tarih')['course']['path_strategy'])->toBe('grade_aware');
});

it('XP ödülü node tipine göre artar', function (): void {
    $nodes = collect(pathFor('tyt_tarih')['units'][0]['nodes']);

    $calisma1 = $nodes->firstWhere('title', 'Çalışma 1');
    $challenge = $nodes->firstWhere('type', 'unit_challenge');

    expect($calisma1['xp_reward'])->toBe(10)
        ->and($challenge['xp_reward'])->toBeGreaterThan($calisma1['xp_reward']);
});

it('AYT dersinin yolu da çalışır', function (): void {
    // AYT yolunun lansmanda ispatlanması, 11 AYT dersini yazdıktan
    // sonra keşfetmekten ucuz.
    $data = pathFor('ayt_matematik');

    expect($data['course']['scope'])->toBe('ayt')
        ->and($data['units'][0]['title'])->toBe('Türev')
        ->and($data['units'][0]['nodes'])->toHaveCount(4);
});

it('yayınlanmamış ders 404 döner', function (): void {
    $course = Course::query()->where('code', 'tyt_fizik')->firstOrFail();

    $this->withToken($this->token)
        ->getJson("/api/v1/courses/{$course->id}/path")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'CONTENT_NOT_AVAILABLE');
});

it('cevap anahtarı yol yanıtında hiçbir yerde geçmez', function (): void {
    $raw = $this->withToken($this->token)
        ->getJson('/api/v1/courses/'.Course::query()->where('code', 'tyt_tarih')->value('id').'/path')
        ->getContent();

    expect($raw)->not->toContain('answer_key')
        ->and($raw)->not->toContain('correct_option_id');
});
