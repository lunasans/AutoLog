<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Repair;
use Illuminate\Http\Request;

class RepairController extends Controller
{
    public function store(Request $request, Car $car)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'odometer_reading' => 'nullable|integer|min:0',
        ]);

        $car->repairs()->create($validated);

        return redirect()->route('dashboard')->with('success', 'Service-Eintrag gespeichert.');
    }

    public function destroy(Repair $repair)
    {
        $repair->delete();
        return redirect()->back()->with('success', 'Service-Eintrag wurde gelöscht.');
    }
}
