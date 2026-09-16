<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\UseCase;

use App\Modules\Admin\Infrastructure\Eloquent\Model\AuditLog;
use App\Shared\Clock\ClockInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Yönetici eylemlerini kaydeder.
 *
 * "Öncesi/sonrası" birlikte tutulur: bir sorunun yanlış hâle geldiği anı
 * bulmak için sadece "değişti" bilgisi yetmez, neyin neye döndüğü gerekir.
 */
final readonly class RecordAudit
{
    public function __construct(private ClockInterface $clock) {}

    /** @param  array<string, mixed>|null  $before */
    public function __invoke(
        ?int $adminUserId,
        string $action,
        Model $entity,
        ?array $before = null,
        ?string $ip = null,
    ): void {
        AuditLog::query()->create([
            'admin_user_id' => $adminUserId,
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->getKey(),
            'before' => $before,
            'after' => $entity->getAttributes(),
            'ip' => $ip,
            'created_at' => $this->clock->now(),
        ]);
    }
}
