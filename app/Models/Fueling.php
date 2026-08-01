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
}
