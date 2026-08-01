<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Reads a workshop invoice so the user does not have to retype it.
 */
interface RepairExtractor
{
    public function extract(UploadedFile $file): ExtractedRepair;

    public function isAvailable(): bool;
}
