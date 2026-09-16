<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation;

/**
 * Doğrulayıcılar arasında tekrar eden kontroller.
 *
 * Trait, her doğrulayıcının aynı kontrolü kendi yazmasını önler — bir
 * tanesinde unutulan "id'ler benzersiz mi" kontrolü, o tipteki soruların
 * sessizce yanlış puanlanması demek.
 */
trait Rules
{
    /** @return list<string> */
    protected function requireText(mixed $value, string $field): array
    {
        return is_string($value) && trim($value) !== ''
            ? []
            : ["{$field} boş olamaz."];
    }

    /**
     * id/text çiftlerinden oluşan listeyi doğrular ve id'leri döner.
     *
     * @return array{0: list<string>, 1: list<string>} [id'ler, hatalar]
     */
    protected function idList(mixed $value, string $field, int $min): array
    {
        if (! is_array($value) || count($value) < $min) {
            return [[], ["{$field} en az {$min} öğe içermeli."]];
        }

        $ids = [];
        $errors = [];

        foreach ($value as $index => $item) {
            if (! is_array($item) || ! isset($item['id']) || ! is_scalar($item['id'])) {
                $errors[] = "{$field}[{$index}] için 'id' gerekli.";

                continue;
            }

            if (! isset($item['text']) || ! is_string($item['text']) || trim($item['text']) === '') {
                $errors[] = "{$field}[{$index}] için 'text' boş olamaz.";
            }

            $ids[] = (string) $item['id'];
        }

        if (count($ids) !== count(array_unique($ids))) {
            $errors[] = "{$field} içinde tekrar eden id var.";
        }

        return [$ids, $errors];
    }

    /**
     * Sıralama cevabının tüm öğeleri tam olarak bir kez içerdiğini doğrular.
     *
     * @param  list<string>  $itemIds
     * @return list<string>
     */
    protected function requireExactOrder(mixed $order, array $itemIds, string $field): array
    {
        if (! is_array($order)) {
            return ["{$field} bir dizi olmalı."];
        }

        $given = array_map(strval(...), array_values($order));
        $expected = $itemIds;

        sort($given);
        sort($expected);

        return $given === $expected
            ? []
            : ["{$field}, öğelerin hepsini tam olarak bir kez içermeli."];
    }
}
