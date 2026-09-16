<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock;

/**
 * unit_nodes.unlock_rule JSON'unun çalışan hâli.
 *
 * Composite pattern: AllOf/AnyOf başka kuralları sarar. İçerik ekibi panelden
 * kural ağacı kurgular, kod deploy'u gerekmez. Yeni kural türü = yeni sınıf +
 * fabrikada bir satır; mevcut hiçbir kural açılmaz (OCP).
 */
interface UnlockRule
{
    public function evaluate(UnlockContext $context): UnlockVerdict;
}
