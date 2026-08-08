<?php

namespace App\Services\Receipts;

/**
 * One parking session read off a document. A provider invoice (EasyPark and
 * the like) lists many of these; a single paper ticket yields one.
 *
 * Location and cost are what an entry cannot do without, so only those two are
 * required for a session to be usable - times and plate are frequently absent.
 */
class ExtractedParkingSession
{
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?string $location = null,
        public readonly ?float $cost = null,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null,
        /**
         * The provider's own charge for handling this session, where the
         * document bills it separately. Kept apart from the parking fee so it
         * can be shown as what it is, and summed as what was actually paid.
         */
        public readonly ?float $fee = null,
        /** Invoices covering several cars say which one parked. */
        public readonly ?string $licensePlate = null,
    ) {}

    /** What the account was actually charged for this session. */
    public function total(): ?float
    {
        if ($this->cost === null) {
            return null;
        }

        return round($this->cost + ($this->fee ?? 0), 2);
    }

    /** The attributes of the entry this session becomes. */
    public function toAttributes(): array
    {
        return [
            'date' => $this->date,
            'location' => $this->location,
            'cost' => $this->total(),
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }

    /** Whether this session carries enough to become an entry on its own. */
    public function isComplete(): bool
    {
        return $this->date !== null && $this->location !== null && $this->cost !== null;
    }

    /** A copy with the provider's charge for this session added. */
    public function withFee(float $fee): self
    {
        return new self(
            $this->date, $this->location, $this->cost,
            $this->startTime, $this->endTime,
            round(($this->fee ?? 0) + $fee, 2), $this->licensePlate,
        );
    }
}
