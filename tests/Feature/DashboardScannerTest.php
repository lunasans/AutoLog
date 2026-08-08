<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The quick forms on the dashboard read receipts just like the entry forms on
 * the car page. They exist once per car, so the wiring has to be per car id -
 * a single shared id would leave every card but the first without a scanner.
 */
class DashboardScannerTest extends TestCase
{
    use RefreshDatabase;

    private function car(User $user, string $plate): Car
    {
        return $user->cars()->create([
            'brand' => 'VW',
            'model' => 'Polo',
            'year' => 2010,
            'license_plate' => $plate,
            'initial_odometer' => 0,
        ]);
    }

    public function test_every_card_carries_its_own_parking_scanner(): void
    {
        $user = User::factory()->create();
        $first = $this->car($user, 'M-AB 1234');
        $second = $this->car($user, 'M-CD 5678');

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        foreach ([$first, $second] as $car) {
            $this->assertStringContainsString('id="parking-form-'.$car->id.'"', $html);
            $this->assertStringContainsString('id="parking-receipt-'.$car->id.'"', $html);
            $this->assertStringContainsString('id="parking-scan-status-'.$car->id.'"', $html);
            $this->assertStringContainsString("status: 'parking-scan-status-{$car->id}'", $html);
        }
    }

    public function test_the_fueling_quick_form_reads_receipts_too(): void
    {
        $user = User::factory()->create();
        $car = $this->car($user, 'M-AB 1234');

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="fuel-form-'.$car->id.'"', $html);
        $this->assertStringContainsString("status: 'fuel-scan-status-{$car->id}'", $html);
    }

    public function test_the_scanner_script_is_loaded(): void
    {
        $user = User::factory()->create();
        $this->car($user, 'M-AB 1234');

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('js/receipt-scanner.js', escape: false);
    }

    public function test_an_empty_garage_renders_without_a_scanner(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('wireReceiptScanner(', escape: false);
    }
}
