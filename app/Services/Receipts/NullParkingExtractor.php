<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Used when no API key is configured. Parking tickets can still be entered by
 * hand and their receipts uploaded - only the reading is missing.
 */
class NullParkingExtractor implements ParkingExtractor
{
    public function extract(UploadedFile $file): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
