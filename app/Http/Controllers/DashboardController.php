<?php

namespace App\Http\Controllers;

use App\Services\Receipts\ParkingExtractor;
use App\Services\Receipts\ReceiptExtractor;
use App\Services\Receipts\RepairExtractor;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $cars = $request->user()->cars()->with(['fuelings', 'repairs', 'parkingTickets'])->get();

        $stats = $cars->map(function ($car) {
            // Fuel, workshop and parking costs are reported separately - they
            // behave differently and lumping them together hides which is which.
            $totalFuel = $car->fuelings->sum('price_total');
            $totalRepairs = $car->repairs->sum('cost');
            $totalParking = $car->parkingTickets->sum('cost');

            // Safely calculate HU urgency
            $huUrgent = false;
            try {
                if ($car->hu_due_at) {
                    $huUrgent = $car->hu_due_at->isBefore(now()->addMonth());
                }
            } catch (\Exception $e) {
                // Log or ignore if the date is still unparsable
            }

            return [
                'car' => $car,
                'total_fuel' => $totalFuel,
                'total_repairs' => $totalRepairs,
                'total_parking' => $totalParking,
                'total_spent' => $totalFuel + $totalRepairs + $totalParking,
                'avg_consumption' => $car->average_consumption,
                'hu_urgent' => $huUrgent,
            ];
        });

        // The quick forms read receipts just like the ones on the car page.
        $canScanReceipts = app(ReceiptExtractor::class)->isAvailable();
        $canScanRepairs = app(RepairExtractor::class)->isAvailable();
        $canScanParking = app(ParkingExtractor::class)->isAvailable();

        return view('dashboard', compact('stats', 'canScanReceipts', 'canScanRepairs', 'canScanParking'));
    }
}
