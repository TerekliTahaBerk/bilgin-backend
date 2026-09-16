<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation;

/** @param list<string> $errors */
final readonly class ContentValidationResult
{
    /** @param  list<string>  $errors */
    private function __construct(public array $errors) {}

    public static function valid(): self
    {
        return new self([]);
    }

    /** @param  list<string>  $errors */
    public static function invalid(array $errors): self
    {
        return new self($errors);
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }
}
