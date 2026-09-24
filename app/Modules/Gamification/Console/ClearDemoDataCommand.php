<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Console;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `demo:seed` ile üretilen sahte öğrencileri siler.
 *
 * Demo verisinin geri alınabilir olması şart: aksi hâlde "bir kereliğine
 * deneyelim" diye eklenen sahte kullanıcılar üretim istatistiklerine
 * kalıcı olarak karışır ve hangisinin gerçek olduğu bir daha ayırt
 * edilemez.
 */
final class ClearDemoDataCommand extends Command
{
    protected $signature = 'demo:clear {--force : Onay sorma}';

    protected $description = 'demo:seed ile üretilen sahte öğrencileri siler';

    public function handle(): int
    {
        $ids = DB::table('devices')
            ->where('device_identifier', 'like', SeedDemoDataCommand::DEVICE_PREFIX.'%')
            ->pluck('user_id')
            ->unique()
            ->all();

        if ($ids === []) {
            $this->components->info('Silinecek demo kullanıcı yok.');

            return self::SUCCESS;
        }

        $count = count($ids);

        if (! $this->option('force') && ! $this->confirm("{$count} demo kullanıcı silinecek. Devam?")) {
            return self::SUCCESS;
        }

        // forceDelete ŞART: User yumuşak silme kullanıyor ve `delete()`
        // satırı bırakır. Kalan satırla birlikte lig üyeliği, cihaz kaydı ve
        // XP defteri de durur — yani "temizledim" denip hiçbir şey
        // temizlenmemiş olur. Demo verisinde iz bırakmamak tek amaç.
        User::query()->whereIn('id', $ids)->forceDelete();

        $this->components->info("{$count} demo kullanıcı silindi.");

        return self::SUCCESS;
    }
}
