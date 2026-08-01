<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
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

    public function test_a_single_fueling_yields_an_average_consumption(): void
    {
        $car = $this->car(User::factory()->create());

        // 200 km on 20.35 L -> 10.18 L/100km
        $car->fuelings()->create([
            'date' => '2026-07-30',
            'liters' => 20.35,
            'price_total' => 42.51,
            'odometer_reading' => 1200,
        ]);

        $this->assertSame(10.18, $car->fresh()->average_consumption);
    }

    public function test_average_consumption_spans_every_fueling(): void
    {
        $car = $this->car(User::factory()->create());

        $car->fuelings()->create([
            'date' => '2026-07-30', 'liters' => 20.0, 'price_total' => 40.0, 'odometer_reading' => 1200,
        ]);
        $car->fuelings()->create([
            'date' => '2026-08-10', 'liters' => 30.0, 'price_total' => 60.0, 'odometer_reading' => 1600,
        ]);

        // 50 L over 600 km -> 8.33 L/100km
        $this->assertSame(8.33, $car->fresh()->average_consumption);
    }

    public function test_a_car_without_fuelings_reports_zero(): void
    {
        $this->assertSame(0, $this->car(User::factory()->create())->average_consumption);
    }

    public function test_fuel_and_repair_costs_are_reported_separately(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $car->fuelings()->create([
            'date' => '2026-07-30', 'liters' => 20.35, 'price_total' => 42.51, 'odometer_reading' => 1200,
        ]);
        $car->repairs()->create([
            'date' => '2026-07-31', 'description' => 'Ölwechsel', 'cost' => 129.9,
        ]);

        $stats = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->viewData('stats');

        $this->assertEqualsWithDelta(42.51, $stats[0]['total_fuel'], 0.001);
        $this->assertEqualsWithDelta(129.9, $stats[0]['total_repairs'], 0.001);
        $this->assertEqualsWithDelta(172.41, $stats[0]['total_spent'], 0.001);
    }
}
