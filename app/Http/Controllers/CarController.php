<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Services\Receipts\ReceiptExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    public function index()
    {
        return redirect()->route('dashboard');
    }

    public function create()
    {
        return view('cars.create');
    }

    public function show(Car $car)
    {
        Gate::authorize('view', $car);

        $car->load(['fuelings' => function($q) {
            $q->orderBy('date', 'desc')->orderBy('id', 'desc');
        }, 'repairs' => function($q) {
            $q->orderBy('date', 'desc')->orderBy('id', 'desc');
        }]);

        // Fueling Data for Chart (Sorted Ascending for Timeline)
        $chartData = $car->fuelings->sortBy([['date', 'asc'], ['odometer_reading', 'asc']])->values();

        $fuelLabels = [];
        $fuelConsumption = [];

        // The first fueling is measured against the car's initial odometer -
        // the same baseline FuelingController uses when writing the entry - so
        // a car with a single fueling still charts a data point.
        for ($i = 0; $i < count($chartData); $i++) {
            $current = $chartData[$i];
            $baseOdometer = $i === 0 ? $car->initial_odometer : $chartData[$i - 1]->odometer_reading;
            $distance = $current->odometer_reading - $baseOdometer;

            if ($distance > 0) {
                $consumption = ($current->liters / $distance) * 100;
                $fuelLabels[] = \Carbon\Carbon::parse($current->date)->format('d.m.');
                $fuelConsumption[] = round($consumption, 2);
            }
        }

        $canScanReceipts = app(ReceiptExtractor::class)->isAvailable();

        return view('cars.show', compact('car', 'fuelLabels', 'fuelConsumption', 'canScanReceipts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));

        $request->user()->cars()->create($validated);

        return redirect()->route('dashboard')->with('success', 'Auto erfolgreich hinzugefügt.');
    }

    public function edit(Car $car)
    {
        Gate::authorize('update', $car);

        return view('cars.edit', compact('car'));
    }

    public function update(Request $request, Car $car)
    {
        Gate::authorize('update', $car);

        $validated = $request->validate($this->rules($request, $car));

        $car->update($validated);

        return redirect()->route('dashboard')->with('success', 'Auto erfolgreich aktualisiert.');
    }

    public function destroy(Car $car)
    {
        Gate::authorize('delete', $car);

        $car->delete();
        return redirect()->route('dashboard')->with('success', 'Fahrzeug wurde erfolgreich entfernt.');
    }

    /**
     * Validation rules shared by store() and update().
     * The plate is unique per owner only, so plates of other users stay invisible.
     */
    private function rules(Request $request, ?Car $car = null): array
    {
        $plateUnique = Rule::unique('cars', 'license_plate')
            ->where(fn ($q) => $q->where('user_id', $request->user()->id));

        if ($car) {
            $plateUnique->ignore($car->id);
        }

        return [
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'license_plate' => ['required', 'string', 'max:255', $plateUnique],
            'vin' => 'nullable|string|max:255',
            'hu_due_at' => 'nullable|string|max:30',
            'initial_odometer' => 'required|integer|min:0',
        ];
    }
}
