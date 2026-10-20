<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hesabı ve ona bağlı tüm kişisel veriyi siler (KVKK md. 7 / GDPR md. 17).
 *
 * `forceDelete` bilinçli. `User` modeli `SoftDeletes` kullanıyor ama silme
 * TALEBİ karşısında yumuşak silme bir cevap değildir: veri hâlâ orada
 * durur, `users.email` üzerindeki tekil dizin aynı adresle yeniden kayıt
 * olmayı engeller ve kullanıcıya söylenen şey doğru olmaz.
 *
 * Bağlı tabloların tamamı (`user_enrollments`, ilerleme, oturumlar,
 * cihazlar, kimlikler, lig üyelikleri — 20 yabancı anahtar) `cascadeOnDelete`
 * ya da `nullOnDelete` ile tanımlı; tek bir silme hepsini temizliyor. Bu
 * sınıfın elle tablo dolaşmaması, şemanın doğru kurulmuş olmasından.
 *
 * Geri alınamaz. Çağıran uç bunu kullanıcıya açıkça söylemek zorunda.
 */
final readonly class DeleteAccount
{
    public function __invoke(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            $user = User::query()->findOrFail($userId);

            /*
             | Jetonlar önce iptal ediliyor. Silme sırasında başka bir
             | cihazdan gelen eşzamanlı istek, yarıda kalmış bir kullanıcıya
             | yazmaya çalışırdı.
             */
            $user->tokens()->delete();

            /*
             | Denetim izi: hangi hesabın silindiği değil, bir silmenin
             | GERÇEKLEŞTİĞİ kaydediliyor. Kimliği buraya yazmak, silinen
             | veriyi günlüklerde yeniden üretmek olurdu.
             */
            Log::info('account.deleted', [
                'was_guest' => $user->is_guest,
                'had_email' => $user->email !== null,
                'days_active' => $user->created_at?->diffInDays(now()) ?? 0,
            ]);

            $user->forceDelete();
        });
    }
}
