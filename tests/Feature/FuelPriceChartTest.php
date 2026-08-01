<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelPriceChartTest extends TestCase
{
    use RefreshDatabase;

    private function car(User $user): Car
    {
        return $user->cars()->create([
            'brand' => 'VW',
            'model' => 'Polo',
            'year' => 2010,
            'license_plate' => 'GL-MS 141',
            'initial_odometer' => 1000,
        ]);
    }

    public function test_the_price_per_liter_follows_from_the_stored_figures(): void
    {
        $fueling = $this->car(User::factory()->create())->fuelings()->create([
            'date' => '2026-07-30',
            'liters' => 20.35,
            'price_total' => 42.51,
            'odometer_reading' => 1200,
        ]);

        // The receipt printed 2,089 EUR/Liter.
        $this->assertSame(2.089, $fueling->price_per_liter);
    }

    public function test_a_fueling_without_liters_has_no_price_per_liter(): void
    {
        $fueling = $this->car(User::factory()->create())->fuelings()->create([
            'date' => '2026-07-30',
            'liters' => 0,
            'price_total' => 42.51,
            'odometer_reading' => 1200,
        ]);

        $this->assertNull($fueling->price_per_liter);
    }

    public function test_a_single_fueling_already_charts_a_price(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $car->fuelings()->create([
            'date' => '2026-07-30', 'liters' => 20.35, 'price_total' => 42.51, 'odometer_reading' => 1200,
        ]);

        $this->actingAs($user)
            ->get(route('cars.show', $car))
            ->assertOk()
            ->assertViewHas('pricePerLiter', [2.089])
            ->assertViewHas('priceLabels', ['30.07.26']);
    }

    public function test_prices_are_charted_in_date_order(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        // Entered out of order, as happens when catching up on receipts.
        $car->fuelings()->create([
            'date' => '2026-08-10', 'liters' => 25.0, 'price_total' => 50.0, 'odometer_reading' => 1600,
        ]);
        $car->fuelings()->create([
            'date' => '2026-07-30', 'liters' => 20.0, 'price_total' => 42.0, 'odometer_reading' => 1200,
        ]);

        $this->actingAs($user)
            ->get(route('cars.show', $car))
            ->assertOk()
            ->assertViewHas('pricePerLiter', [2.1, 2.0])
            ->assertViewHas('priceLabels', ['30.07.26', '10.08.26']);
    }

    public function test_a_car_without_fuelings_charts_no_prices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cars.show', $this->car($user)))
            ->assertOk()
            ->assertViewHas('pricePerLiter', [])
            ->assertSee('Noch keine Tankvorgänge erfasst.');
    }
}
