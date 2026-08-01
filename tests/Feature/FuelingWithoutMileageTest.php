<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelingWithoutMileageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Car $car;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->car = $this->user->cars()->create([
            'brand' => 'VW',
            'model' => 'Polo',
            'year' => 2010,
            'license_plate' => 'GL-MS 141',
            'initial_odometer' => 1000,
        ]);
    }

    private function log(array $attributes)
    {
        return $this->actingAs($this->user)
            ->post(route('fuelings.store', $this->car), $attributes + [
                'date' => '2026-07-30',
                'liters' => 20.0,
                'price_total' => 40.0,
            ]);
    }

    public function test_a_fueling_can_be_recorded_without_the_distance_driven(): void
    {
        $this->log([])->assertRedirect();

        $this->assertNull($this->car->fuelings()->sole()->odometer_reading);
    }

    public function test_such_an_entry_still_counts_towards_costs_and_prices(): void
    {
        $this->log(['liters' => 20.0, 'price_total' => 42.0]);

        $this->actingAs($this->user)
            ->get(route('cars.show', $this->car))
            ->assertOk()
            ->assertViewHas('pricePerLiter', [2.1])
            // No distance, so nothing to say about consumption.
            ->assertViewHas('fuelConsumption', []);
    }

    public function test_it_does_not_shift_the_mileage_of_later_entries(): void
    {
        $this->log(['date' => '2026-08-10', 'trip_km' => 300]);
        $later = $this->car->fuelings()->sole();

        $this->log(['date' => '2026-07-30']);

        $this->assertSame(1300, $later->fresh()->odometer_reading);
    }

    public function test_its_liters_belong_to_the_next_measurable_stretch(): void
    {
        // 20 L with no mileage, then 30 L over 500 km. The fuel burned on that
        // stretch is all 50 L, not just the 30 that came with a reading.
        $this->log(['date' => '2026-07-30', 'liters' => 20.0]);
        $this->log(['date' => '2026-08-10', 'liters' => 30.0, 'trip_km' => 500]);

        // 50 L / 500 km -> 10 L/100km
        $this->assertSame(10.0, $this->car->fresh()->average_consumption);

        $this->actingAs($this->user)
            ->get(route('cars.show', $this->car))
            ->assertViewHas('fuelConsumption', [10.0]);
    }

    public function test_deleting_such_an_entry_leaves_other_mileages_alone(): void
    {
        $this->log(['date' => '2026-07-30', 'trip_km' => 200]);
        $this->log(['date' => '2026-08-10']);

        $withoutMileage = $this->car->fuelings()->whereNull('odometer_reading')->sole();
        $withMileage = $this->car->fuelings()->whereNotNull('odometer_reading')->sole();

        $this->actingAs($this->user)
            ->delete(route('fuelings.destroy', $withoutMileage))
            ->assertRedirect();

        $this->assertSame(1200, $withMileage->fresh()->odometer_reading);
        $this->assertSame(1, $this->car->fuelings()->count());
    }

    public function test_the_distance_is_still_optional_but_validated_when_given(): void
    {
        $this->log(['trip_km' => -5])->assertSessionHasErrors('trip_km');

        $this->assertSame(0, $this->car->fuelings()->count());
    }
}
