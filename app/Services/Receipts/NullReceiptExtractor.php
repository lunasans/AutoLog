<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Used when no API key is configured. Keeps the app fully functional - receipt
 * upload still works, only the auto-fill is missing.
 */
class NullReceiptExtractor implements ReceiptExtractor
{
    public function extract(UploadedFile $file): ExtractedReceipt
    {
        return ExtractedReceipt::empty();
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
