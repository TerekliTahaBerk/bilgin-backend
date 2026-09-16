<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Application\UseCase\SelectionRuleReport;
use App\Modules\Catalog\Application\UseCase\ValidateSelectionRule;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Console\Command;

/**
 * Yayın kapısının CI ve operasyon yüzü.
 *
 * Her yayındaki node'un selection_rule'unu kuru çalıştırır ve yeterli soru
 * getirmeyenleri raporlar. CI'da çalıştığında, havuzu boşaltan bir içerik
 * değişikliği production'a çıkmadan yakalanır.
 */
final class ValidateContentCommand extends Command
{
    protected $signature = 'content:validate
                            {--course= : Yalnızca bu ders kodunu kontrol et}
                            {--all : Taslak node\'ları da dahil et}';

    protected $description = 'Yayındaki node\'ların soru havuzunun yeterliliğini doğrular';

    public function handle(ValidateSelectionRule $validate): int
    {
        $nodes = UnitNode::query()
            ->with(['unit.course'])
            ->when(! $this->option('all'), fn ($q) => $q->where('status', PublishStatus::Published))
            ->when($this->option('course'), fn ($q, $code) => $q->whereHas(
                'unit.course', fn ($c) => $c->where('code', $code)
            ))
            ->orderBy('unit_id')
            ->orderBy('sort_order')
            ->get();

        if ($nodes->isEmpty()) {
            $this->warn('Kontrol edilecek node bulunamadı.');

            return self::SUCCESS;
        }

        $reports = $nodes->map(fn (UnitNode $node): SelectionRuleReport => $validate($node));
        $failed = $reports->reject->passes();

        $this->table(
            ['Ders', 'Ünite', 'Node', 'Gerekli', 'Havuz', 'Durum'],
            $reports->map(fn (SelectionRuleReport $r): array => [
                $r->courseName,
                $r->unitTitle,
                $r->nodeTitle,
                $r->required,
                $r->available,
                $r->passes() ? ($r->relaxed ? 'gevşetildi' : 'OK') : 'HATA',
            ])->all(),
        );

        foreach ($failed as $report) {
            $this->error("[{$report->courseName} · {$report->nodeTitle}] {$report->message()}");
        }

        $this->newLine();
        $this->line(sprintf(
            '%d node kontrol edildi, %d hata.',
            $reports->count(),
            $failed->count(),
        ));

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
