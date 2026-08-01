<?php

namespace App\Services\Receipts;

/**
 * Sanity checks for values a model read off a document. Anything that isn't a
 * usable value becomes null - the form then asks the user for it, which beats
 * prefilling a field with nonsense.
 */
class Value
{
    public static function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public static function positiveFloat(mixed $value): ?float
    {
        return is_numeric($value) && $value > 0 ? (float) $value : null;
    }

    public static function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && $value > 0 ? (int) $value : null;
    }

    /**
     * Repairs store their description in a VARCHAR(255), so an over-long
     * summary would fail validation after the user hits save.
     */
    public static function text(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $value));

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
