<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Fueling;
use App\Services\FuelingLedger;
use App\Services\Receipts\ReceiptExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FuelingController extends Controller
{
    public function __construct(private readonly FuelingLedger $ledger) {}

    public function store(Request $request, Car $car)
    {
        Gate::authorize('update', $car);

        $validated = $this->validated($request);

        $fueling = $this->ledger->add(
            $car,
            collect($validated)->only(['date', 'liters', 'price_total'])->all(),
            $validated['trip_km'] ?? null,
        );

        if ($request->hasFile('receipt')) {
            $fueling->storeReceipt($request->file('receipt'));
            $fueling->save();
        }

        return redirect()->back()->with('success', 'Tankvorgang gespeichert.');
    }

    /**
     * Creates one entry per uploaded receipt. Aimed at catching up on a pile of
     * filed receipts, where typing each one in is the actual obstacle.
     *
     * Receipts carry no mileage, so entries land without one - they count
     * towards costs and fuel price, and can be completed later from the
     * history. Anything unreadable is reported rather than guessed at.
     */
    public function import(Request $request, Car $car, ReceiptExtractor $extractor)
    {
        Gate::authorize('update', $car);

        $validated = $request->validate([
            'receipts' => 'required|array|max:24',
            'receipts.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $created = 0;
        $skipped = 0;
        $unreadable = [];

        foreach ($validated['receipts'] as $file) {
            $receipt = $extractor->extract($file);

            if (! $receipt->isComplete()) {
                $unreadable[] = $file->getClientOriginalName();

                continue;
            }

            // Importing the same folder twice shouldn't double the history.
            $exists = $car->fuelings()
                ->where('date', $receipt->date)
                ->where('price_total', $receipt->priceTotal)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $fueling = $this->ledger->add($car, [
                'date' => $receipt->date,
                'liters' => $receipt->liters,
                'price_total' => $receipt->priceTotal,
            ], null);

            $fueling->storeReceipt($file);
            $fueling->save();

            $created++;
        }

        return redirect()->route('cars.show', $car)
            ->with('success', $this->importSummary($created, $skipped, $unreadable));
    }

    /** @param  list<string>  $unreadable */
    private function importSummary(int $created, int $skipped, array $unreadable): string
    {
        $parts = [$created === 1 ? '1 Eintrag angelegt' : "{$created} Einträge angelegt"];

        if ($skipped > 0) {
            $parts[] = $skipped === 1
                ? '1 Beleg war schon erfasst'
                : "{$skipped} Belege waren schon erfasst";
        }

        if ($unreadable !== []) {
            $parts[] = 'nicht lesbar: '.implode(', ', $unreadable);
        }

        return implode(' · ', $parts).'. Die gefahrenen Kilometer fehlen noch – die kannst du je Eintrag nachtragen.';
    }

    public function edit(Fueling $fueling)
    {
        Gate::authorize('update', $fueling);

        return view('fuelings.edit', [
            'fueling' => $fueling,
            'car' => $fueling->car,
            // Stored as a reading, entered as a distance - derive it back.
            'tripKm' => $this->ledger->tripKm($fueling),
        ]);
    }

    public function update(Request $request, Fueling $fueling)
    {
        Gate::authorize('update', $fueling);

        $validated = $this->validated($request);

        $this->ledger->revise(
            $fueling,
            collect($validated)->only(['date', 'liters', 'price_total'])->all(),
            $validated['trip_km'] ?? null,
        );

        if ($request->hasFile('receipt')) {
            $fueling->storeReceipt($request->file('receipt'));
            $fueling->save();
        } elseif ($request->boolean('remove_receipt')) {
            $fueling->deleteReceipt();
            $fueling->save();
        }

        return redirect()->route('cars.show', $fueling->car)
            ->with('success', 'Tankvorgang aktualisiert.');
    }

    public function receipt(Fueling $fueling)
    {
        Gate::authorize('view', $fueling);

        abort_unless($fueling->hasReceipt(), 404);

        return Storage::disk('local')->download($fueling->receipt_path, $fueling->receipt_name);
    }

    public function destroy(Fueling $fueling)
    {
        Gate::authorize('delete', $fueling);

        $this->ledger->remove($fueling);

        return redirect()->back()->with('success', 'Tankvorgang wurde gelöscht.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'date' => 'required|date',
            'liters' => 'required|numeric|min:0.1',
            'price_total' => 'required|numeric|min:0.1',
            // Optional: receipts filed long after the fact rarely come with the
            // distance driven, and a missing figure beats an invented one.
            'trip_km' => 'nullable|numeric|min:0.1',
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);
    }
}
