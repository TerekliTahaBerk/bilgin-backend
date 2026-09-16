<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading;

/**
 * Sorunun gövdesi ve cevap anahtarı — yalnızca sunucu tarafında bir arada olur.
 * answer_key istemciye giden hiçbir yapıya konmaz.
 */
final readonly class ExerciseContent
{
    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $answerKey
     */
    public function __construct(
        public array $content,
        public array $answerKey,
        public ?string $explanation = null,
    ) {}

    public function key(string $name): mixed
    {
        return $this->answerKey[$name] ?? null;
    }
}
