<?php

namespace App\Models;

use App\Models\Concerns\HasReceipt;
use Illuminate\Database\Eloquent\Model;

class ParkingTicket extends Model
{
    use HasReceipt;

    protected $fillable = ['car_id', 'date', 'location', 'cost', 'start_time', 'end_time'];

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * The parked period as "08:15 – 10:30", or null when the slip carried no
     * times. A half-filled pair still reads usefully, so it is kept.
     */
    public function getParkedPeriodAttribute(): ?string
    {
        $from = $this->start_time ? substr($this->start_time, 0, 5) : null;
        $to = $this->end_time ? substr($this->end_time, 0, 5) : null;

        if (! $from && ! $to) {
            return null;
        }

        return trim(($from ?? '?').' – '.($to ?? '?'));
    }
}
