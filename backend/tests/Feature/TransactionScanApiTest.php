<?php

namespace Tests\Feature;

use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Enums\ScannerCommand;
use App\Enums\TransactionStatus;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\UnitItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TransactionScanApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bearer_token_scan_adds_one_item_by_barcode_without_changing_stock(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->unit('OUT-A');
        $item = $this->item('WATER', 'SCAN-111');
        $this->unitItem($unit, $item, '5000.00');
        $admin = $this->account('admin', $unit);
        $cart = $this->cart($unit, $admin);
        $token = $admin->createToken('esp32-demo', ['transaction:scan'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/transactions/'.$cart->id.'/scans', [
            'barcode' => '  SCAN-111  ',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $cart->id)
            ->assertJsonPath('data.items.0.item.id', $item->id)
            ->assertJsonPath('data.items.0.item.item_code', 'WATER')
            ->assertJsonPath('data.items.0.item.barcode', 'SCAN-111')
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.items.0.input_method', 'SCANNER');
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $cart->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'input_method' => 'SCANNER',
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_scanning_checkout_command_completes_payment_through_the_checkout_service(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->unit('OUT-A');
        $item = $this->item('PARKING', 'SCAN-SERVICE', ItemType::Service);
        $this->unitItem($unit, $item, '5000.00');
        $admin = $this->account('admin', $unit);
        $cart = $this->cart($unit, $admin);
        $token = $admin->createToken('esp32-demo', ['transaction:scan'])->plainTextToken;

        $this->withToken($token)->postJson('/api/transactions/'.$cart->id.'/scans', [
            'barcode' => 'SCAN-SERVICE',
        ])->assertOk();

        $this->withToken($token)->postJson('/api/transactions/'.$cart->id.'/scans', [
            'barcode' => ScannerCommand::Checkout->value,
        ])->assertOk()
            ->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.payer.id', $admin->id)
            ->assertJsonPath('data.total', '5000.00')
            ->assertJsonPath('data.scanner_checkout_barcode', ScannerCommand::Checkout->value);

        $this->assertDatabaseHas('transactions', [
            'id' => $cart->id,
            'status' => 'PAID',
            'paid_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'TRANSACTION_PAID',
            'subject_id' => $cart->id,
            'actor_id' => $admin->id,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_scan_returns_401_without_authentication(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->unit('OUT-A');
        $admin = $this->account('admin', $unit);
        $cart = $this->cart($unit, $admin);

        $response = $this->postJson('/api/transactions/'.$cart->id.'/scans', [
            'barcode' => 'SCAN-111',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_scan_returns_403_when_token_lacks_scanner_ability(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->unit('OUT-A');
        $admin = $this->account('admin', $unit);
        $cart = $this->cart($unit, $admin);
        $token = $admin->createToken('read-only-device', ['transaction:read'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/transactions/'.$cart->id.'/scans', [
            'barcode' => 'SCAN-111',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_scan_returns_403_for_a_cart_owned_by_another_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->unit('OUT-A');
        $owner = $this->account('admin', $unit);
        $scannerUser = $this->account('admin', $unit);
        $cart = $this->cart($unit, $owner);
        $token = $scannerUser->createToken('esp32-demo', ['transaction:scan'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/transactions/'.$cart->id.'/scans', [
            'barcode' => 'SCAN-111',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_scan_returns_409_when_scanner_is_disabled_for_the_unit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->unit('OUT-A', scannerEnabled: false);
        $item = $this->item('WATER', 'SCAN-111');
        $this->unitItem($unit, $item, '5000.00');
        $admin = $this->account('admin', $unit);
        $cart = $this->cart($unit, $admin);
        $token = $admin->createToken('esp32-demo', ['transaction:scan'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/transactions/'.$cart->id.'/scans', [
            'barcode' => 'SCAN-111',
        ]);

        $response->assertConflict();
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_scan_returns_422_when_barcode_is_missing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unit = $this->unit('OUT-A');
        $admin = $this->account('admin', $unit);
        $cart = $this->cart($unit, $admin);
        $token = $admin->createToken('esp32-demo', ['transaction:scan'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/transactions/'.$cart->id.'/scans');

        $response->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_scan_returns_422_when_barcode_is_not_active_in_the_cart_unit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unitA = $this->unit('OUT-A');
        $unitB = $this->unit('OUT-B');
        $item = $this->item('OTHER', 'SCAN-222');
        $this->unitItem($unitB, $item, '6000.00');
        $admin = $this->account('admin', $unitA);
        $cart = $this->cart($unitA, $admin);
        $token = $admin->createToken('esp32-demo', ['transaction:scan'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/transactions/'.$cart->id.'/scans', [
            'barcode' => 'SCAN-222',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->assertDatabaseCount('transaction_items', 0);
    }

    private function unit(string $code, bool $scannerEnabled = true): OperationalUnit
    {
        return OperationalUnit::create([
            'code' => $code,
            'name' => $code,
            'type' => OperationalUnitType::Outlet,
            'scanner_enabled' => $scannerEnabled,
        ]);
    }

    private function item(
        string $code,
        string $barcode,
        ItemType $type = ItemType::Product,
    ): Item {
        return Item::create([
            'item_code' => $code,
            'barcode' => $barcode,
            'name' => $code,
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function unitItem(OperationalUnit $unit, Item $item, string $price): UnitItem
    {
        return UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'selling_price' => $price,
            'is_active' => true,
        ]);
    }

    private function account(string $roleSlug, OperationalUnit $unit): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'operational_unit_id' => $unit->id,
        ])->save();

        return $user->refresh();
    }

    private function cart(OperationalUnit $unit, User $creator): Transaction
    {
        return Transaction::create([
            'transaction_number' => 'TRX-DEMO-'.$creator->id,
            'operational_unit_id' => $unit->id,
            'status' => TransactionStatus::Cart,
            'created_by' => $creator->id,
            'subtotal' => '0.00',
            'total' => '0.00',
            'visitor_count' => 0,
        ]);
    }
}
