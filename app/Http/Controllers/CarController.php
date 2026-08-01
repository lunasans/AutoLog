<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Services\Receipts\ReceiptExtractor;
use App\Services\Receipts\RepairExtractor;
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

        $car->load(['fuelings' => function ($q) {
            $q->orderBy('date', 'desc')->orderBy('id', 'desc');
        }, 'repairs' => function ($q) {
            $q->orderBy('date', 'desc')->orderBy('id', 'desc');
        }]);

        // The history lists what the user typed in - the distance driven - not
        // the running odometer we derive from it.
        $tripDistances = $car->tripDistances();

        $fuelLabels = [];
        $fuelConsumption = [];

        // Shares its arithmetic with the average shown on the dashboard, so the
        // chart and the headline figure can never disagree.
        foreach ($car->consumptionStretches() as $stretch) {
            $fuelLabels[] = \Carbon\Carbon::parse($stretch['date'])->format('d.m.');
            $fuelConsumption[] = $stretch['consumption'];
        }

        // Unlike consumption, a price needs no distance to measure against, so
        // every fueling contributes a point - including ones recorded without
        // a mileage, and the very first one.
        $priceLabels = [];
        $pricePerLiter = [];

        foreach ($car->fuelings->sortBy([['date', 'asc'], ['id', 'asc']])->values() as $fueling) {
            if ($fueling->price_per_liter !== null) {
                $priceLabels[] = \Carbon\Carbon::parse($fueling->date)->format('d.m.y');
                $pricePerLiter[] = $fueling->price_per_liter;
            }
        }

        // Fuel receipts can be read from a PDF text layer without an API key,
        // so the two are enabled independently.
        $canScanReceipts = app(ReceiptExtractor::class)->isAvailable();
        $canScanRepairs = app(RepairExtractor::class)->isAvailable();

        return view('cars.show', compact(
            'car', 'fuelLabels', 'fuelConsumption', 'priceLabels', 'pricePerLiter',
            'tripDistances', 'canScanReceipts', 'canScanRepairs'
        ));
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
            'year' => 'nullable|integer|min:1900|max:'.(date('Y') + 1),
            'license_plate' => ['required', 'string', 'max:255', $plateUnique],
            'vin' => 'nullable|string|max:255',
            'hu_due_at' => 'nullable|string|max:30',
            'initial_odometer' => 'required|integer|min:0',
        ];
    }
}
