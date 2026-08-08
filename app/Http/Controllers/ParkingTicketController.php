<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\ParkingTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ParkingTicketController extends Controller
{
    public function store(Request $request, Car $car)
    {
        Gate::authorize('update', $car);

        $validated = $request->validate([
            'date' => 'required|date',
            'location' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'start_time' => 'nullable|date_format:H:i',
            // Parking across midnight is normal, so the end is not required to
            // come after the start.
            'end_time' => 'nullable|date_format:H:i',
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $ticket = $car->parkingTickets()->create(collect($validated)->except('receipt')->all());

        if ($request->hasFile('receipt')) {
            $ticket->storeReceipt($request->file('receipt'));
            $ticket->save();
        }

        return redirect()->route('dashboard')->with('success', 'Parkticket gespeichert.');
    }

    public function receipt(ParkingTicket $parkingTicket)
    {
        Gate::authorize('view', $parkingTicket);

        abort_unless($parkingTicket->hasReceipt(), 404);

        return Storage::disk('local')->download($parkingTicket->receipt_path, $parkingTicket->receipt_name);
    }

    public function destroy(ParkingTicket $parkingTicket)
    {
        Gate::authorize('delete', $parkingTicket);

        $parkingTicket->delete();

        return redirect()->back()->with('success', 'Parkticket wurde gelöscht.');
    }
}
