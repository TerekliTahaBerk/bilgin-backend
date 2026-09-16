<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enum;

/**
 * unit_nodes.selection_rule.mode — node'un sorularını nereden alacağı.
 *
 * Mini Challenge, Hızlı Tekrar, Ünite Challenge, Sınav Provası ve adaptif
 * zorluk ayrı özellikler değil; aynı motorun farklı modlarıdır. Her mod bir
 * ExerciseSelector implementasyonuna karşılık gelir.
 */
enum SelectionMode: string
{
    case Pool = 'pool';
    case Fixed = 'fixed';
    case ReviewQueue = 'review_queue';
    case Adaptive = 'adaptive';
    case Blueprint = 'blueprint';
}
