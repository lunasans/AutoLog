<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Fueling;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the odometer chain consistent.
 *
 * Fuelings store a reading, not the distance driven - each entry's distance is
 * the gap to the one before it. Adding, revising or removing an entry in the
 * middle therefore has to move every later reading by the same amount, or the
 * whole history after it silently drifts.
 *
 * Every mutation is expressed as detach-then-attach so the three operations
 * share one piece of arithmetic instead of each reimplementing it.
 */
class FuelingLedger
{
    /** @param  array<string, mixed>  $attributes  date, liters, price_total */
    public function add(Car $car, array $attributes, ?float $tripKm): Fueling
    {
        return DB::transaction(function () use ($car, $attributes, $tripKm) {
            $fueling = $car->fuelings()->create($attributes + [
                'odometer_reading' => $this->readingFor($car, $attributes['date'], null, $tripKm),
            ]);

            $this->pushLater($car, $attributes['date'], $tripKm);

            return $fueling;
        });
    }

    /** @param  array<string, mixed>  $attributes  date, liters, price_total */
    public function revise(Fueling $fueling, array $attributes, ?float $tripKm): void
    {
        DB::transaction(function () use ($fueling, $attributes, $tripKm) {
            $car = $fueling->car;

            // Take the old distance out first: later readings move back, so the
            // anchor for the new date is measured against a clean chain. This
            // also makes moving an entry to another date fall out for free.
            $this->pullLater($fueling);

            $fueling->fill($attributes);
            $fueling->odometer_reading = $this->readingFor($car, $attributes['date'], $fueling->id, $tripKm);
            $fueling->save();

            $this->pushLater($car, $attributes['date'], $tripKm);
        });
    }

    public function remove(Fueling $fueling): void
    {
        DB::transaction(function () use ($fueling) {
            $this->pullLater($fueling);
            $fueling->delete();
        });
    }

    /**
     * The distance an entry currently accounts for - the gap to the reading
     * before it. Null when it was recorded without one.
     */
    public function tripKm(Fueling $fueling): ?float
    {
        if ($fueling->odometer_reading === null) {
            return null;
        }

        return $fueling->odometer_reading - $this->baseOdometer(
            $fueling->car, $fueling->date, $fueling->id
        );
    }

    /**
     * Where an entry sits in the chain: the last reading on or before its date.
     * Entries recorded without a distance carry no reading to anchor on.
     */
    private function baseOdometer(Car $car, mixed $date, ?int $excludeId): int
    {
        $previous = $car->fuelings()
            ->whereNotNull('odometer_reading')
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $previous ? $previous->odometer_reading : $car->initial_odometer;
    }

    private function readingFor(Car $car, mixed $date, ?int $excludeId, ?float $tripKm): ?int
    {
        return $tripKm === null
            ? null
            : (int) round($this->baseOdometer($car, $date, $excludeId) + $tripKm);
    }

    /** Everything recorded later moved forward by this entry's distance. */
    private function pushLater(Car $car, mixed $date, ?float $tripKm): void
    {
        if ($tripKm === null || $tripKm <= 0) {
            return;
        }

        // Rows without a reading are left alone - incrementing NULL yields NULL.
        $car->fuelings()->where('date', '>', $date)->increment('odometer_reading', $tripKm);
    }

    /** Close the gap this entry's distance leaves behind. */
    private function pullLater(Fueling $fueling): void
    {
        $tripKm = $this->tripKm($fueling);

        if ($tripKm === null || $tripKm <= 0) {
            return;
        }

        $fueling->car->fuelings()
            ->where('id', '!=', $fueling->id)
            ->where('date', '>', $fueling->date)
            ->decrement('odometer_reading', $tripKm);
    }
}
