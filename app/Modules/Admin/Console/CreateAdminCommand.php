<?php

declare(strict_types=1);

namespace App\Modules\Admin\Console;

use App\Modules\Admin\Domain\Enum\AdminRole;
use App\Modules\Admin\Infrastructure\Eloquent\Model\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Üretimde ilk yönetici hesabını açar.
 *
 * Bu komut olmadan production'a çıkmak imkânsız: seeder yalnızca
 * local/testing'de çalışıyor, `POST /admins` ise zaten oturum açmış bir
 * süper yönetici istiyor. Yumurta-tavuk problemi ancak sunucuda
 * çalıştırılan bir komutla kırılır.
 *
 * Şifre argüman olarak da verilebilir ama VERİLMEMELİ: komut satırı
 * geçmişine ve süreç listesine düşer. Varsayılan, gizli soru sormaktır.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
                            {--name= : Görünen ad}
                            {--email= : E-posta}
                            {--role=super_admin : Rol}
                            {--password= : Şifre (önerilmez — gizli soru sorulur)}';

    protected $description = 'Yönetici hesabı oluşturur (ilk kurulum için)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Ad');
        $email = $this->option('email') ?: $this->ask('E-posta');
        $role = (string) $this->option('role');

        $password = $this->option('password') ?: $this->secret('Şifre (en az 12 karakter)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'role' => $role],
            [
                'name' => ['required', 'string', 'max:191'],
                'email' => ['required', 'email', 'unique:admin_users,email'],
                'password' => ['required', 'string', 'min:12'],
                'role' => ['required', 'string', 'in:'.implode(',', array_column(AdminRole::cases(), 'value'))],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = AdminUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => AdminRole::from($role),
            'is_active' => true,
        ]);

        $this->info("Hesap açıldı: {$admin->email} · {$admin->role->label()}");

        if ($admin->role !== AdminRole::SuperAdmin) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Bundan sonraki hesapları panelden açabilirsin (Yöneticiler ekranı).');

        return self::SUCCESS;
    }
}
