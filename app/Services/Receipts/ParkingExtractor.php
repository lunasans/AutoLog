<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Reads the parking sessions off a document. Unlike the fuel and workshop
 * extractors this returns a list: a provider invoice covers a whole month of
 * parking, and each session becomes its own entry.
 */
interface ParkingExtractor
{
    /** @return list<ExtractedParkingSession> */
    public function extract(UploadedFile $file): array;

    public function isAvailable(): bool;
}
