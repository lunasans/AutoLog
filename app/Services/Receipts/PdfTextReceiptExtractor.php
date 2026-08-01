<?php

namespace App\Services\Receipts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

/**
 * Reads a fuel receipt straight out of a PDF's text layer.
 *
 * Receipts issued by the fuel provider are generated documents, not scans, so
 * the numbers are already text - no model, no cost, no guessing. Patterns are
 * anchored on labels that appear once ("TOTAL", the litre column), so a receipt
 * that is laid out differently yields nothing rather than a wrong number, and
 * the caller falls through to the vision extractor.
 */
class PdfTextReceiptExtractor implements ReceiptExtractor
{
    /** The metered litre column, e.g. "*   20,35 Liter  SÄULENNUMMER  6  *". */
    private const LITERS = '/(\d{1,3}(?:[.,]\d{1,3})?)\s+Liter\b/u';

    /** The paid total on its own line, e.g. " TOTAL      42,51 EUR". */
    private const TOTAL = '/^\s*TOTAL\s+(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*EUR/mu';

    /** Same amount, on receipts that print a VAT breakdown instead. */
    private const GROSS = '/\bBRUTTO\s+(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*EUR/u';

    /** Footer timestamp in local time, e.g. "#19119 30.07.26 16:24". */
    private const LOCAL_DATE = '/#\d+\s+(\d{2})\.(\d{2})\.(\d{2})\s+\d{2}:\d{2}/u';

    /**
     * Fallback: the fiscal module's timestamp. It is UTC, so it can name the
     * previous day for a late-night fill-up - only used if the footer is gone.
     */
    private const FISCAL_DATE = '/;(\d{4}-\d{2}-\d{2})T\d{2}:\d{2}/u';

    public function __construct(private readonly Parser $parser) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function extract(UploadedFile $file): ExtractedReceipt
    {
        if ($file->getMimeType() !== 'application/pdf') {
            return ExtractedReceipt::empty();
        }

        try {
            $text = $this->parser->parseFile($file->getRealPath())->getText();
        } catch (\Throwable $e) {
            // Encrypted, malformed, or image-only PDFs land here. Not an error
            // worth surfacing - the next extractor gets a turn.
            Log::debug('PDF text layer unreadable', ['exception' => $e]);

            return ExtractedReceipt::empty();
        }

        return $this->fromText($text);
    }

    /**
     * Split out from extract() so the patterns can be exercised against receipt
     * text directly, without checking a real receipt into the repository.
     */
    public function fromText(string $text): ExtractedReceipt
    {
        return new ExtractedReceipt(
            date: $this->date($text),
            liters: $this->amount($text, self::LITERS),
            priceTotal: $this->amount($text, self::TOTAL) ?? $this->amount($text, self::GROSS),
        );
    }

    private function date(string $text): ?string
    {
        if (preg_match(self::LOCAL_DATE, $text, $m)) {
            // Two-digit years on till receipts are always this century.
            return sprintf('20%s-%s-%s', $m[3], $m[2], $m[1]);
        }

        return preg_match(self::FISCAL_DATE, $text, $m) ? $m[1] : null;
    }

    private function amount(string $text, string $pattern): ?float
    {
        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $raw = $m[1];

        // German notation: the comma is the decimal mark and dots group
        // thousands. Without a comma a lone dot is the decimal mark itself -
        // stripping it would turn 20.35 litres into 2035.
        if (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', str_replace('.', '', $raw));
        }

        $value = (float) $raw;

        return $value > 0 ? $value : null;
    }
}
