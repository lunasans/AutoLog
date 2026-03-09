<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    protected $fillable = ['brand', 'model', 'year', 'license_plate', 'vin', 'hu_due_at', 'initial_odometer'];

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
        
        // Using a reliable CDN for car logos (e.g. clearbit or a similar service)
        // Note: This is an example, in a real app you might use a specific car logo API
        return "https://logo.clearbit.com/{$brand}.com";
    }
}
