<?php

namespace App\Http\Controllers;

use App\Services\Receipts\ExtractedParkingSession;
use App\Services\Receipts\ParkingExtractor;
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
        private readonly ParkingExtractor $parking,
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

    /**
     * Fills the parking form from a single document. A provider invoice bills
     * many sessions at once and cannot be squeezed into one form - the answer
     * says so, and the page points at the import instead of prefilling one of
     * them and losing the rest.
     */
    public function parking(Request $request): JsonResponse
    {
        if (! $this->parking->isAvailable()) {
            return $this->unavailable();
        }

        $sessions = array_values(array_filter(
            $this->parking->extract($this->validated($request)),
            fn (ExtractedParkingSession $session) => $session->isComplete(),
        ));

        if (count($sessions) > 1) {
            return response()->json(['sessions' => count($sessions)]);
        }

        return response()->json(
            ($sessions[0] ?? new ExtractedParkingSession)->toAttributes() + ['sessions' => count($sessions)]
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
