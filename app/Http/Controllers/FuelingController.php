<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Fueling;
use Illuminate\Http\Request;

class FuelingController extends Controller
{
    public function store(Request $request, Car $car)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'liters' => 'required|numeric|min:0.1',
            'price_total' => 'required|numeric|min:0.1',
            'trip_km' => 'required|numeric|min:0.1',
        ]);

        // Get the latest odometer reading
        $lastFueling = $car->fuelings()->orderBy('odometer_reading', 'desc')->first();
        $lastOdometer = $lastFueling ? $lastFueling->odometer_reading : $car->initial_odometer;

        $newOdometer = $lastOdometer + $validated['trip_km'];

        $car->fuelings()->create([
            'date' => $validated['date'],
            'liters' => $validated['liters'],
            'price_total' => $validated['price_total'],
            'odometer_reading' => $newOdometer,
        ]);

        return redirect()->back()->with('success', 'Tankvorgang gespeichert.');
    }

    public function destroy(Fueling $fueling)
    {
        $fueling->delete();
        return redirect()->back()->with('success', 'Tankvorgang wurde gelöscht.');
    }
}
