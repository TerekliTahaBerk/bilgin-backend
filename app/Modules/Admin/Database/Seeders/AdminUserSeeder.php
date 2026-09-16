<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Seeders;

use App\Modules\Admin\Domain\Enum\AdminRole;
use App\Modules\Admin\Infrastructure\Eloquent\Model\AdminUser;
use Illuminate\Database\Seeder;

/**
 * Yerel geliştirme hesapları.
 *
 * Şifreler ortam değişkeninden okunur ve production'da bu seeder hiç
 * çalıştırılmaz (DatabaseSeeder yalnızca local/testing'de çağırır).
 */
final class AdminUserSeeder extends Seeder
{
    private const ACCOUNTS = [
        ['Süper Yönetici', 'admin@tekrarla.test', AdminRole::SuperAdmin],
        ['İçerik Editörü', 'editor@tekrarla.test', AdminRole::ContentEditor],
        ['İçerik Denetçisi', 'denetci@tekrarla.test', AdminRole::ContentReviewer],
    ];

    public function run(): void
    {
        $password = (string) config('tekrarla.admin.seed_password');

        foreach (self::ACCOUNTS as [$name, $email, $role]) {
            AdminUser::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $password, 'role' => $role, 'is_active' => true],
            );
        }
    }
}
