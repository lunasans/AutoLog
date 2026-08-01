<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;

/**
 * Tries cheap extractors before expensive ones.
 *
 * A provider-issued PDF is read from its text layer for free; only a scan or a
 * layout the patterns don't cover reaches the vision model, which costs money
 * per call. The first complete result wins - a partial one is kept only if
 * nothing better follows, so a half-read receipt never suppresses a full read.
 */
class ChainedReceiptExtractor implements ReceiptExtractor
{
    /** @param  iterable<ReceiptExtractor>  $extractors  cheapest first */
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

    public function extract(UploadedFile $file): ExtractedReceipt
    {
        $best = ExtractedReceipt::empty();

        foreach ($this->extractors as $extractor) {
            if (! $extractor->isAvailable()) {
                continue;
            }

            $result = $extractor->extract($file);

            if ($result->isComplete()) {
                return $result;
            }

            if ($best->isEmpty()) {
                $best = $result;
            }
        }

        return $best;
    }
}
