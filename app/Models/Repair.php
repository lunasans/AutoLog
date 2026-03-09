<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repair extends Model
{
    protected $fillable = ['car_id', 'date', 'description', 'cost', 'odometer_reading'];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}
