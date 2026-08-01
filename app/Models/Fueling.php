<?php

namespace App\Models;

use App\Models\Concerns\HasReceipt;
use Illuminate\Database\Eloquent\Model;

class Fueling extends Model
{
    use HasReceipt;

    protected $fillable = ['car_id', 'date', 'liters', 'price_total', 'odometer_reading'];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * Price per litre in euros. Not stored - it follows from the two figures
     * the receipt actually shows, so there is nothing to keep in sync.
     */
    public function getPricePerLiterAttribute(): ?float
    {
        if (! $this->liters || $this->liters <= 0) {
            return null;
        }

        return round($this->price_total / $this->liters, 3);
    }
}
