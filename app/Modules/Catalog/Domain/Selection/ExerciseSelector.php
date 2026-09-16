<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection;

use App\Modules\Catalog\Domain\Enum\SelectionMode;

/**
 * Bir node'un sorularının nereden geleceğini çözen strateji.
 *
 * Mini Challenge, Hızlı Tekrar, Ünite Challenge, Sınav Provası ve adaptif
 * zorluk ayrı özellikler değil — bu arayüzün farklı implementasyonlarıdır.
 * Yeni bir mod eklemek hiçbir mevcut sınıfı açmaz (OCP).
 */
interface ExerciseSelector
{
    public function mode(): SelectionMode;

    public function select(SelectionRule $rule, SelectionContext $context): SelectionResult;
}
