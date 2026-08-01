<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Repair;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RepairController extends Controller
{
    public function store(Request $request, Car $car)
    {
        Gate::authorize('update', $car);

        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'odometer_reading' => 'nullable|integer|min:0',
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $repair = $car->repairs()->create(collect($validated)->except('receipt')->all());

        if ($request->hasFile('receipt')) {
            $repair->storeReceipt($request->file('receipt'));
            $repair->save();
        }

        return redirect()->route('dashboard')->with('success', 'Service-Eintrag gespeichert.');
    }

    public function receipt(Repair $repair)
    {
        Gate::authorize('view', $repair);

        abort_unless($repair->hasReceipt(), 404);

        return Storage::disk('local')->download($repair->receipt_path, $repair->receipt_name);
    }

    public function destroy(Repair $repair)
    {
        Gate::authorize('delete', $repair);

        $repair->delete();
        return redirect()->back()->with('success', 'Service-Eintrag wurde gelöscht.');
    }
}
