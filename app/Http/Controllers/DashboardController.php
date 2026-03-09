<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $cars = Car::with(['fuelings', 'repairs'])->get();

        $stats = $cars->map(function ($car) {
            $totalFuel = $car->fuelings->sum('price_total');
            $totalLiters = $car->fuelings->sum('liters');
            $totalRepairs = $car->repairs->sum('cost');
            
            // Calculate avg consumption (L/100km)
            $avgConsumption = 0;
            if ($car->fuelings->count() > 0) {
                $lastOdo = $car->fuelings->max('odometer_reading');
                $distance = $lastOdo - $car->initial_odometer;
                if ($distance > 0) {
                    $avgConsumption = ($totalLiters / $distance) * 100;
                }
            }

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
                'avg_consumption' => round($avgConsumption, 2),
                'hu_urgent' => $huUrgent,
            ];
        });

        return view('dashboard', compact('stats'));
    }
}
