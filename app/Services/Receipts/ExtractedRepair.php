<?php

namespace App\Services\Receipts;

/**
 * What we could read off a workshop invoice. Unlike a fuel receipt these are
 * scanned, so a partial read is common and every field stays optional.
 */
class ExtractedRepair
{
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?string $description = null,
        public readonly ?float $cost = null,
        public readonly ?int $odometerReading = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'description' => $this->description,
            'cost' => $this->cost,
            'odometer_reading' => $this->odometerReading,
        ];
    }
}
