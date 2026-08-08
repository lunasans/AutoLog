<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParkingTicketTest extends TestCase
{
    use RefreshDatabase;

    private function car(User $user, string $plate = 'GL-MS 141'): Car
    {
        return $user->cars()->create([
            'brand' => 'VW',
            'model' => 'Polo',
            'year' => 2010,
            'license_plate' => $plate,
            'initial_odometer' => 1000,
        ]);
    }

    public function test_a_parking_ticket_can_be_logged(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $this->actingAs($user)->post(route('parking-tickets.store', $car), [
            'date' => '2026-08-08',
            'location' => 'Parkhaus Zentrum',
            'cost' => 4.5,
            'start_time' => '08:15',
            'end_time' => '10:30',
        ])->assertRedirect(route('dashboard'));

        $ticket = $car->parkingTickets()->sole();

        $this->assertSame('Parkhaus Zentrum', $ticket->location);
        $this->assertEqualsWithDelta(4.5, $ticket->cost, 0.001);
        $this->assertSame('08:15 – 10:30', $ticket->parked_period);
    }

    public function test_times_are_optional(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $this->actingAs($user)->post(route('parking-tickets.store', $car), [
            'date' => '2026-08-08',
            'location' => 'Straße',
            'cost' => 1.2,
        ])->assertRedirect(route('dashboard'));

        $this->assertNull($car->parkingTickets()->sole()->parked_period);
    }

    public function test_an_uploaded_receipt_is_stored_privately_and_served_by_route(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $car = $this->car($user);

        $this->actingAs($user)->post(route('parking-tickets.store', $car), [
            'date' => '2026-08-08',
            'location' => 'Parkhaus Zentrum',
            'cost' => 4.5,
            'receipt' => UploadedFile::fake()->create('parkschein.jpg', 100, 'image/jpeg'),
        ])->assertRedirect(route('dashboard'));

        $ticket = $car->parkingTickets()->sole();

        Storage::disk('local')->assertExists($ticket->receipt_path);
        $this->assertSame('parkschein.jpg', $ticket->receipt_name);

        $this->actingAs($user)->get(route('parking-tickets.receipt', $ticket))->assertOk();
    }

    public function test_another_user_can_neither_log_read_nor_delete(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $car = $this->car($owner);
        $ticket = $car->parkingTickets()->create([
            'date' => '2026-08-08', 'location' => 'Parkhaus Zentrum', 'cost' => 4.5,
        ]);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('parking-tickets.store', $car), [
            'date' => '2026-08-08', 'location' => 'X', 'cost' => 1,
        ])->assertForbidden();
        $this->actingAs($intruder)->get(route('parking-tickets.receipt', $ticket))->assertForbidden();
        $this->actingAs($intruder)->delete(route('parking-tickets.destroy', $ticket))->assertForbidden();

        $this->assertSame(1, $car->parkingTickets()->count());
    }

    public function test_deleting_a_ticket_removes_its_receipt(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $car = $this->car($user);

        $this->actingAs($user)->post(route('parking-tickets.store', $car), [
            'date' => '2026-08-08',
            'location' => 'Parkhaus Zentrum',
            'cost' => 4.5,
            'receipt' => UploadedFile::fake()->create('parkschein.jpg', 100, 'image/jpeg'),
        ]);

        $ticket = $car->parkingTickets()->sole();
        $path = $ticket->receipt_path;

        $this->actingAs($user)->delete(route('parking-tickets.destroy', $ticket));

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, $car->parkingTickets()->count());
    }

    public function test_deleting_the_car_cleans_up_parking_receipts(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $car = $this->car($user);

        $this->actingAs($user)->post(route('parking-tickets.store', $car), [
            'date' => '2026-08-08',
            'location' => 'Parkhaus Zentrum',
            'cost' => 4.5,
            'receipt' => UploadedFile::fake()->create('parkschein.jpg', 100, 'image/jpeg'),
        ]);

        $path = $car->parkingTickets()->sole()->receipt_path;

        $this->actingAs($user)->delete(route('cars.destroy', $car));

        Storage::disk('local')->assertMissing($path);
    }

    public function test_parking_costs_are_reported_separately_on_the_dashboard(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user);

        $car->fuelings()->create([
            'date' => '2026-07-30', 'liters' => 20.35, 'price_total' => 42.51, 'odometer_reading' => 1200,
        ]);
        $car->repairs()->create([
            'date' => '2026-07-31', 'description' => 'Ölwechsel', 'cost' => 129.9,
        ]);
        $car->parkingTickets()->create([
            'date' => '2026-08-01', 'location' => 'Parkhaus Zentrum', 'cost' => 4.5,
        ]);

        $stats = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->viewData('stats');

        $this->assertEqualsWithDelta(4.5, $stats[0]['total_parking'], 0.001);
        $this->assertEqualsWithDelta(176.91, $stats[0]['total_spent'], 0.001);
    }
}
