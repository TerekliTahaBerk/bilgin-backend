<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading;

/**
 * İstemciden gelen ham cevap. Şekli egzersiz tipine göre değişir:
 *   {"option_id":"b"} · {"blanks":["Töre"]} · {"pairs":{"1":"x"}}
 *   {"order":["1","2"]} · {"value":42} · {"known":true}
 */
final readonly class SubmittedAnswer
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public array $payload,
        public int $elapsedMs = 0,
    ) {}

    public function get(string $key): mixed
    {
        return $this->payload[$key] ?? null;
    }

    public function string(string $key): ?string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : null;
    }

    /** @return list<string> */
    public function stringList(string $key): array
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn ($v): string => is_scalar($v) ? (string) $v : '',
            $value,
        ));
    }

    /** @return array<string, string> */
    public function stringMap(string $key): array
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $k => $v) {
            if (is_scalar($v)) {
                $map[(string) $k] = (string) $v;
            }
        }

        return $map;
    }
}
