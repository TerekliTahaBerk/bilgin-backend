<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use RuntimeException;

final class ValidatorRegistry
{
    /** @var array<string, ExerciseContentValidator> */
    private array $validators = [];

    /** @param  iterable<ExerciseContentValidator>  $validators */
    public function __construct(iterable $validators = [])
    {
        foreach ($validators as $validator) {
            $this->register($validator);
        }
    }

    public function register(ExerciseContentValidator $validator): void
    {
        $this->validators[$validator->type()->value] = $validator;
    }

    public function for(ExerciseType $type): ExerciseContentValidator
    {
        return $this->validators[$type->value]
            ?? throw new RuntimeException("Bu egzersiz tipi için doğrulayıcı yok: {$type->value}");
    }
}
