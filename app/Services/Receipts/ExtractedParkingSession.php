<?php

namespace App\Services\Receipts;

/**
 * One parking session read off a document. A provider invoice (EasyPark and
 * the like) lists many of these; a single paper ticket yields one.
 *
 * Location and cost are what an entry cannot do without, so only those two are
 * required for a session to be usable - times are frequently absent.
 */
class ExtractedParkingSession
{
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?string $location = null,
        public readonly ?float $cost = null,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null,
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'location' => $this->location,
            'cost' => $this->cost,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }

    /** Whether this session carries enough to become an entry on its own. */
    public function isComplete(): bool
    {
        return $this->date !== null && $this->location !== null && $this->cost !== null;
    }
}
