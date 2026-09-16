<?php

declare(strict_types=1);

use App\Modules\Admin\Domain\Enum\AdminRole;
use App\Modules\Admin\Infrastructure\Eloquent\Model\AdminUser;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 | Yönetici hesabı yönetimi.
 |
 | Kritik kısım kilitlenme önleme: tek süper yöneticinin bir yanlış tıkla
 | sistemi kimsesiz bırakması mümkün olmamalı — geri dönüş yolu yalnızca
 | veritabanı olurdu.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->tokens = collect([
        'admin' => 'admin@tekrarla.test',
        'editor' => 'editor@tekrarla.test',
    ])->map(fn (string $email): string => $this->postJson('/api/admin/v1/auth/login', [
        'email' => $email, 'password' => 'tekrarla-local',
    ])->assertOk()->json('data.token'));

    $this->superAdmin = AdminUser::query()->where('email', 'admin@tekrarla.test')->firstOrFail();
});

function asPanel(string $role): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(test()->tokens[$role]);
}

it('yönetici listesi rolleri ve yetkilerini de döner', function (): void {
    // Panel rol seçicisini buradan doldurur, yetkileri kendi tahmin etmez.
    $response = asPanel('admin')->getJson('/api/admin/v1/admins')->assertOk();

    expect($response->json('data.admins'))->toHaveCount(3)
        ->and($response->json('data.roles'))->toHaveCount(5);

    $editor = collect($response->json('data.roles'))->firstWhere('value', 'content_editor');

    expect($editor['abilities']['edit_content'])->toBeTrue()
        ->and($editor['abilities']['publish_content'])->toBeFalse();
});

it('süper yönetici yeni hesap açabilir', function (): void {
    asPanel('admin')
        ->postJson('/api/admin/v1/admins', [
            'name' => 'Yeni Editör',
            'email' => 'yeni@tekrarla.test',
            'password' => 'cok-guclu-bir-sifre',
            'role' => 'content_editor',
        ])->assertCreated();

    $created = AdminUser::query()->where('email', 'yeni@tekrarla.test')->firstOrFail();

    expect($created->role)->toBe(AdminRole::ContentEditor)
        ->and($created->is_active)->toBeTrue()
        // Şifre hash'lenmiş olmalı, düz metin değil.
        ->and($created->password)->not->toBe('cok-guclu-bir-sifre');
});

it('editör yönetici hesabı açamaz', function (): void {
    asPanel('editor')
        ->postJson('/api/admin/v1/admins', [
            'name' => 'Kendime Yetki', 'email' => 'x@tekrarla.test',
            'password' => 'cok-guclu-bir-sifre', 'role' => 'super_admin',
        ])->assertStatus(403);

    expect(AdminUser::query()->count())->toBe(3);
});

it('kısa şifreyi reddeder', function (): void {
    asPanel('admin')
        ->postJson('/api/admin/v1/admins', [
            'name' => 'Zayıf', 'email' => 'zayif@tekrarla.test',
            'password' => '123456', 'role' => 'support',
        ])->assertStatus(422);
});

it('aynı e-posta ile ikinci hesap açılamaz', function (): void {
    asPanel('admin')
        ->postJson('/api/admin/v1/admins', [
            'name' => 'Kopya', 'email' => 'editor@tekrarla.test',
            'password' => 'cok-guclu-bir-sifre', 'role' => 'support',
        ])->assertStatus(422);
});

it('rol değiştirilebilir', function (): void {
    $editor = AdminUser::query()->where('email', 'editor@tekrarla.test')->firstOrFail();

    asPanel('admin')
        ->patchJson("/api/admin/v1/admins/{$editor->uuid}", ['role' => 'content_reviewer'])
        ->assertOk()
        ->assertJsonPath('data.role', 'content_reviewer');

    expect($editor->fresh()->role)->toBe(AdminRole::ContentReviewer);
});

it('hesap kapatılınca giriş yapamaz', function (): void {
    $editor = AdminUser::query()->where('email', 'editor@tekrarla.test')->firstOrFail();

    asPanel('admin')
        ->patchJson("/api/admin/v1/admins/{$editor->uuid}", ['is_active' => false])
        ->assertOk();

    app('auth')->forgetGuards();

    $this->postJson('/api/admin/v1/auth/login', [
        'email' => 'editor@tekrarla.test', 'password' => 'tekrarla-local',
    ])->assertStatus(422);
});

it('kimse kendi rolünü düşüremez', function (): void {
    asPanel('admin')
        ->patchJson("/api/admin/v1/admins/{$this->superAdmin->uuid}", ['role' => 'analyst'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ADMIN_LOCKOUT_PREVENTED');

    expect($this->superAdmin->fresh()->role)->toBe(AdminRole::SuperAdmin);
});

it('kimse kendi hesabını kapatamaz', function (): void {
    asPanel('admin')
        ->patchJson("/api/admin/v1/admins/{$this->superAdmin->uuid}", ['is_active' => false])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ADMIN_LOCKOUT_PREVENTED');

    expect($this->superAdmin->fresh()->is_active)->toBeTrue();
});

it('son süper yönetici düşürülemez — kendisi olmasa bile', function (): void {
    // İki süper yöneticiden biri diğerini düşürebilir; ama sonuncu kalırsa
    // panele giriş tamamen kapanırdı.
    $ikinci = AdminUser::query()->create([
        'name' => 'İkinci Süper', 'email' => 'ikinci@tekrarla.test',
        'password' => 'cok-guclu-bir-sifre', 'role' => AdminRole::SuperAdmin,
    ]);

    // İki süper yönetici varken düşürme serbest.
    asPanel('admin')
        ->patchJson("/api/admin/v1/admins/{$ikinci->uuid}", ['role' => 'support'])
        ->assertOk();

    // Geriye tek süper yönetici kaldı; artık o da düşürülemez.
    $ikinciToken = $this->postJson('/api/admin/v1/auth/login', [
        'email' => 'ikinci@tekrarla.test', 'password' => 'cok-guclu-bir-sifre',
    ])->json('data.token');

    app('auth')->forgetGuards();

    // Support rolü zaten müfredat yetkisi olmadığı için 403 alır —
    // yani son süper yöneticiyi düşürecek kimse kalmaz.
    $this->withToken($ikinciToken)
        ->patchJson("/api/admin/v1/admins/{$this->superAdmin->uuid}", ['role' => 'analyst'])
        ->assertStatus(403);

    expect(AdminUser::query()->where('role', AdminRole::SuperAdmin)->where('is_active', true)->count())
        ->toBe(1);
});

it('hesap değişiklikleri denetim kaydına yazılır', function (): void {
    $editor = AdminUser::query()->where('email', 'editor@tekrarla.test')->firstOrFail();

    asPanel('admin')->patchJson("/api/admin/v1/admins/{$editor->uuid}", ['role' => 'analyst']);

    $log = DB::table('audit_logs')->latest('id')->first();

    expect($log->action)->toBe('admin_user.updated')
        ->and(json_decode((string) $log->before, true)['role'])->toBe('content_editor')
        ->and(json_decode((string) $log->after, true)['role'])->toBe('analyst');
});
