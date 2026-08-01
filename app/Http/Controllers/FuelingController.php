<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Fueling;
use App\Services\FuelingLedger;
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
