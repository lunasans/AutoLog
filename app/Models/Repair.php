<?php

namespace App\Models;

use App\Models\Concerns\HasReceipt;
use Illuminate\Database\Eloquent\Model;

class Repair extends Model
{
    use HasReceipt;

    protected $fillable = ['car_id', 'date', 'description', 'cost', 'odometer_reading'];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}
