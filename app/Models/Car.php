<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $fillable = ['user_id', 'brand', 'model', 'year', 'license_plate', 'vin', 'hu_due_at', 'initial_odometer'];

    protected $casts = [
        'hu_due_at' => 'date',
    ];

    public function setHuDueAtAttribute($value)
    {
        if (!$value) {
            $this->attributes['hu_due_at'] = null;
            return;
        }

        try {
            // Support YYYY-MM from browser inputs
            if (preg_match('/^\d{4}-\d{2}$/', $value)) {
                $this->attributes['hu_due_at'] = \Carbon\Carbon::createFromFormat('Y-m', $value)->startOfMonth()->format('Y-m-d');
            } else {
                // Try guessing other formats, fallback to raw if Carbon can't handle it
                $this->attributes['hu_due_at'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // Handle common German month formats if Carbon fails
            $germanMonths = [
                'Januar' => '01', 'Februar' => '02', 'März' => '03', 'April' => '04',
                'Mai' => '05', 'Juni' => '06', 'Juli' => '07', 'August' => '08',
                'September' => '09', 'Oktober' => '10', 'November' => '11', 'Dezember' => '12',
                'Jan' => '01', 'Feb' => '02', 'Mär' => '03', 'Apr' => '04', 'Jun' => '06', 'Jul' => '07', 'Aug' => '08', 'Sep' => '09', 'Okt' => '10', 'Nov' => '11', 'Dez' => '12'
            ];

            foreach ($germanMonths as $de => $num) {
                if (stripos($value, $de) !== false) {
                    $year = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($year) == 4) {
                        $this->attributes['hu_due_at'] = "$year-$num-01";
                        return;
                    }
                }
            }

            // Final fallback
            $this->attributes['hu_due_at'] = null;
        }
    }
    protected static function booted(): void
    {
        // Fuelings and repairs are removed by the database cascade, which skips
        // model events - so their uploaded receipts are cleaned up here.
        static::deleting(function (Car $car) {
            foreach ($car->fuelings as $fueling) {
                $fueling->deleteReceipt();
            }

            foreach ($car->repairs as $repair) {
                $repair->deleteReceipt();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fuelings()
    {
        return $this->hasMany(Fueling::class);
    }

    public function repairs()
    {
        return $this->hasMany(Repair::class);
    }
    /**
     * Get the manufacturer logo URL based on brand name.
     */
    public function getLogoUrlAttribute()
    {
        $brand = strtolower(trim($this->brand));
        $brand = str_replace([' ', '_'], '-', $brand);
        
        // Simple mapping for common variations
        $mapping = [
            'vw' => 'volkswagen',
            'mercedes-benz' => 'mercedes',
            'mercedes benz' => 'mercedes',
        ];
        
        $brand = $mapping[$brand] ?? $brand;

        // Only plain brand slugs may end up in the outgoing URL.
        if (!preg_match('/^[a-z0-9-]+$/', $brand)) {
            return $this->logo_fallback;
        }

        // Using a reliable CDN for car logos (e.g. clearbit or a similar service)
        // Note: This is an example, in a real app you might use a specific car logo API
        return "https://logo.clearbit.com/{$brand}.com";
    }

    /**
     * Consumption for each stretch between two known odometer readings, in
     * date order. The first stretch runs from the car's initial odometer - the
     * same baseline FuelingController writes against.
     *
     * A fueling recorded without a distance still contributes its litres to
     * the stretch it falls in. Dropping them would understate consumption,
     * because the distance they covered is part of the next reading either way.
     *
     * @return list<array{date: mixed, consumption: float, liters: float, distance: int}>
     */
    public function consumptionStretches(): array
    {
        $fuelings = $this->fuelings->sortBy([['date', 'asc'], ['id', 'asc']])->values();

        $stretches = [];
        $lastReading = $this->initial_odometer;
        $liters = 0.0;

        foreach ($fuelings as $fueling) {
            $liters += (float) $fueling->liters;

            if ($fueling->odometer_reading === null) {
                continue;
            }

            $distance = $fueling->odometer_reading - $lastReading;

            // A reading that doesn't advance closes no stretch; its litres roll
            // into the next one rather than being lost.
            if ($distance <= 0) {
                continue;
            }

            $stretches[] = [
                'date' => $fueling->date,
                'consumption' => round(($liters / $distance) * 100, 2),
                'liters' => $liters,
                'distance' => $distance,
            ];

            $lastReading = $fueling->odometer_reading;
            $liters = 0.0;
        }

        return $stretches;
    }

    /**
     * Average consumption in L/100km across every measurable stretch. Litres
     * bought after the last known reading are left out - there is no distance
     * to hold them against yet.
     */
    public function getAverageConsumptionAttribute()
    {
        $stretches = $this->consumptionStretches();

        if ($stretches === []) {
            return 0;
        }

        $liters = array_sum(array_column($stretches, 'liters'));
        $distance = array_sum(array_column($stretches, 'distance'));

        return round(($liters / $distance) * 100, 2);
    }

    /**
     * Local placeholder used when no remote logo can be loaded.
     */
    public function getLogoFallbackAttribute()
    {
        return \App\Support\InitialsAvatar::url($this->brand);
    }
}
