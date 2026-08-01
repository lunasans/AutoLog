<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Fueling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FuelingController extends Controller
{
    public function store(Request $request, Car $car)
    {
        Gate::authorize('update', $car);

        $validated = $request->validate([
            'date' => 'required|date',
            'liters' => 'required|numeric|min:0.1',
            'price_total' => 'required|numeric|min:0.1',
            // Optional: receipts filed long after the fact rarely come with the
            // distance driven, and a missing figure beats an invented one.
            'trip_km' => 'nullable|numeric|min:0.1',
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        DB::transaction(function () use ($car, $validated, $request) {
            $tripKm = $validated['trip_km'] ?? null;

            // Anchor the entry on the last fueling *before* its date, so entries
            // added out of order still land at the right mileage. Entries with
            // no distance of their own carry no reading to anchor on.
            $previous = $car->fuelings()
                ->whereNotNull('odometer_reading')
                ->where('date', '<=', $validated['date'])
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $baseOdometer = $previous ? $previous->odometer_reading : $car->initial_odometer;

            $fueling = $car->fuelings()->create([
                'date' => $validated['date'],
                'liters' => $validated['liters'],
                'price_total' => $validated['price_total'],
                'odometer_reading' => $tripKm === null ? null : $baseOdometer + $tripKm,
            ]);

            if ($request->hasFile('receipt')) {
                $fueling->storeReceipt($request->file('receipt'));
                $fueling->save();
            }

            // Everything recorded later moved by the same distance. Without a
            // distance there is nothing to shift. (Rows with no reading are
            // left alone either way - incrementing NULL yields NULL.)
            if ($tripKm !== null) {
                $car->fuelings()
                    ->where('date', '>', $validated['date'])
                    ->increment('odometer_reading', $tripKm);
            }
        });

        return redirect()->back()->with('success', 'Tankvorgang gespeichert.');
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

        DB::transaction(function () use ($fueling) {
            $car = $fueling->car;

            // An entry recorded without a distance left no gap to close.
            if ($fueling->odometer_reading === null) {
                $fueling->delete();

                return;
            }

            $previous = $car->fuelings()
                ->whereNotNull('odometer_reading')
                ->where('id', '!=', $fueling->id)
                ->where('odometer_reading', '<=', $fueling->odometer_reading)
                ->orderBy('odometer_reading', 'desc')
                ->first();

            $baseOdometer = $previous ? $previous->odometer_reading : $car->initial_odometer;
            $tripKm = $fueling->odometer_reading - $baseOdometer;

            $fueling->delete();

            // Close the gap the removed trip left behind.
            if ($tripKm > 0) {
                $car->fuelings()
                    ->where('odometer_reading', '>', $fueling->odometer_reading)
                    ->decrement('odometer_reading', $tripKm);
            }
        });

        return redirect()->back()->with('success', 'Tankvorgang wurde gelöscht.');
    }
}
