<?php

namespace App\Http\Controllers;

use App\Services\Receipts\ReceiptExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptScanController extends Controller
{
    public function __construct(private readonly ReceiptExtractor $extractor) {}

    /**
     * Reads a receipt and returns suggested form values. The file is only held
     * in the temporary upload location and is never stored - the real upload
     * happens when the user submits the form.
     */
    public function scan(Request $request): JsonResponse
    {
        if (! $this->extractor->isAvailable()) {
            return response()->json(['message' => 'Beleg-Erkennung ist nicht konfiguriert.'], 503);
        }

        // Same limits as the receipt field on the entry forms.
        $validated = $request->validate([
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        return response()->json($this->extractor->extract($validated['receipt'])->toArray());
    }
}
