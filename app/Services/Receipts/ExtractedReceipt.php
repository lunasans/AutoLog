<?php

namespace App\Services\Receipts;

/**
 * What we could read off a receipt. Every field is optional - a crumpled
 * thermal-paper receipt may only yield the total, and that is still useful.
 */
class ExtractedReceipt
{
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?float $liters = null,
        public readonly ?float $priceTotal = null,
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
            'liters' => $this->liters,
            'price_total' => $this->priceTotal,
            'odometer_reading' => $this->odometerReading,
        ];
    }

    /**
     * Whether everything the fueling form needs was found. Odometer readings
     * are not printed on fuel receipts, so they don't count towards this.
     */
    public function isComplete(): bool
    {
        return $this->date !== null && $this->liters !== null && $this->priceTotal !== null;
    }

    public function isEmpty(): bool
    {
        return $this->date === null
            && $this->liters === null
            && $this->priceTotal === null
            && $this->odometerReading === null;
    }
}
