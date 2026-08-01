<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $cars = $request->user()->cars()->with(['fuelings', 'repairs'])->get();

        $stats = $cars->map(function ($car) {
            $totalFuel = $car->fuelings->sum('price_total');
            $totalRepairs = $car->repairs->sum('cost');

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
                'total_spent' => $totalFuel + $totalRepairs,
                'avg_consumption' => $car->average_consumption,
                'hu_urgent' => $huUrgent,
            ];
        });

        return view('dashboard', compact('stats'));
    }
}
