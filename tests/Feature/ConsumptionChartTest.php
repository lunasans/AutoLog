<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumptionChartTest extends TestCase
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

    public function test_a_single_fueling_is_charted_against_the_initial_odometer(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        // 200 km on 20.35 L -> 10.18 L/100km
        $car->fuelings()->create([
            'date' => '2026-07-30',
            'liters' => 20.35,
            'price_total' => 42.51,
            'odometer_reading' => 1200,
        ]);

        $this->actingAs($user)
            ->get(route('cars.show', $car))
            ->assertOk()
            ->assertViewHas('fuelConsumption', [10.18])
            ->assertViewHas('fuelLabels', ['30.07.']);
    }

    public function test_later_fuelings_are_measured_against_their_predecessor(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $car->fuelings()->create([
            'date' => '2026-07-30',
            'liters' => 20.35,
            'price_total' => 42.51,
            'odometer_reading' => 1200,
        ]);

        // 400 km on 30 L -> 7.5 L/100km
        $car->fuelings()->create([
            'date' => '2026-08-10',
            'liters' => 30.0,
            'price_total' => 60.0,
            'odometer_reading' => 1600,
        ]);

        $this->actingAs($user)
            ->get(route('cars.show', $car))
            ->assertOk()
            ->assertViewHas('fuelConsumption', [10.18, 7.5]);
    }

    public function test_a_car_without_fuelings_charts_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cars.show', $this->car($user)))
            ->assertOk()
            ->assertViewHas('fuelConsumption', []);
    }
}
