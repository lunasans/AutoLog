<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;

class CarController extends Controller
{
    public function create()
    {
        return view('cars.create');
    }

    public function show(Car $car)
    {
        $car->load(['fuelings' => function($q) {
            $q->orderBy('date', 'desc')->orderBy('id', 'desc');
        }, 'repairs' => function($q) {
            $q->orderBy('date', 'desc')->orderBy('id', 'desc');
        }]);

        // Fueling Data for Chart (Sorted Ascending for Timeline)
        $chartData = $car->fuelings->sortBy('date')->values();
        
        $fuelLabels = [];
        $fuelConsumption = [];
        
        for ($i = 0; $i < count($chartData); $i++) {
            $current = $chartData[$i];
            
            if ($i === 0) {
                $distance = $current->odometer_reading - $car->initial_odometer;
            } else {
                $distance = $current->odometer_reading - $chartData[$i - 1]->odometer_reading;
            }
            
            if ($distance > 0) {
                $consumption = ($current->liters / $distance) * 100;
                $fuelLabels[] = \Carbon\Carbon::parse($current->date)->format('d.m.');
                $fuelConsumption[] = round($consumption, 2);
            }
        }

        return view('cars.show', compact('car', 'fuelLabels', 'fuelConsumption'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'license_plate' => 'required|string|unique:cars,license_plate',
            'vin' => 'nullable|string|max:255',
            'hu_due_at' => 'nullable|string|max:10',
            'initial_odometer' => 'required|integer|min:0',
        ]);

        Car::create($validated);

        return redirect()->route('dashboard')->with('success', 'Auto erfolgreich hinzugefügt.');
    }

    public function edit(Car $car)
    {
        return view('cars.edit', compact('car'));
    }

    public function update(Request $request, Car $car)
    {
        $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'license_plate' => 'required|string|unique:cars,license_plate,' . $car->id,
            'vin' => 'nullable|string|max:255',
            'hu_due_at' => 'nullable|string|max:10',
            'initial_odometer' => 'required|integer|min:0',
        ]);

        $car->update($validated);

        return redirect()->route('dashboard')->with('success', 'Auto erfolgreich aktualisiert.');
    }

    public function destroy(Car $car)
    {
        $car->delete();
        return redirect()->route('dashboard')->with('success', 'Fahrzeug wurde erfolgreich entfernt.');
    }
}
