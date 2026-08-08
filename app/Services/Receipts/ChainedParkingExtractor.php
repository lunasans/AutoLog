<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Tries cheap extractors before expensive ones.
 *
 * A provider invoice is read from its text layer for free and exactly; only a
 * paper ticket, a screenshot or an unknown provider reaches the vision model,
 * which costs money per call. The first extractor to find any session wins -
 * a document either matches a known layout or it does not.
 */
class ChainedParkingExtractor implements ParkingExtractor
{
    /** @param  iterable<ParkingExtractor>  $extractors  cheapest first */
    public function __construct(private readonly iterable $extractors) {}

    public function isAvailable(): bool
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->isAvailable()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<ExtractedParkingSession> */
    public function extract(UploadedFile $file): array
    {
        foreach ($this->extractors as $extractor) {
            if (! $extractor->isAvailable()) {
                continue;
            }

            $sessions = $extractor->extract($file);

            if ($sessions !== []) {
                return $sessions;
            }
        }

        return [];
    }
}
