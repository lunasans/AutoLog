<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Used when no API key is configured. Workshop invoices are scans, so unlike
 * fuel receipts there is no free fallback - the feature is simply off.
 */
class NullRepairExtractor implements RepairExtractor
{
    public function extract(UploadedFile $file): ExtractedRepair
    {
        return ExtractedRepair::empty();
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
