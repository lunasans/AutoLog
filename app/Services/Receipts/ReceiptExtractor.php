<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Reads the numbers off a fuel receipt so the user does not have to type them.
 * Implementations are swapped in AppServiceProvider - see NullReceiptExtractor
 * for the no-credentials case.
 */
interface ReceiptExtractor
{
    public function extract(UploadedFile $file): ExtractedReceipt;

    /**
     * Whether this extractor can actually do anything right now. The UI hides
     * the scan button when it can't, instead of failing on click.
     */
    public function isAvailable(): bool;
}
