<?php

namespace App\Http\Controllers;

use App\Services\Receipts\ReceiptExtractor;
use App\Services\Receipts\RepairExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reads an uploaded document and returns suggested form values. The file is
 * only held in the temporary upload location and is never stored - the real
 * upload happens when the user submits the entry form.
 */
class ReceiptScanController extends Controller
{
    public function __construct(
        private readonly ReceiptExtractor $receipts,
        private readonly RepairExtractor $repairs,
    ) {}

    public function fueling(Request $request): JsonResponse
    {
        if (! $this->receipts->isAvailable()) {
            return $this->unavailable();
        }

        return response()->json(
            $this->receipts->extract($this->validated($request))->toArray()
        );
    }

    public function repair(Request $request): JsonResponse
    {
        if (! $this->repairs->isAvailable()) {
            return $this->unavailable();
        }

        return response()->json(
            $this->repairs->extract($this->validated($request))->toArray()
        );
    }

    /** Same limits as the receipt field on the entry forms. */
    private function validated(Request $request): \Illuminate\Http\UploadedFile
    {
        return $request->validate([
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ])['receipt'];
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'Beleg-Erkennung ist nicht konfiguriert.'], 503);
    }
}
