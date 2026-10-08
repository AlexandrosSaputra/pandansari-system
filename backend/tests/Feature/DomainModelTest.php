<?php

namespace Tests\Feature;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Enums\StockDirection;
use App\Enums\StockMovementType;
use App\Enums\StockTransferStatus;
use App\Enums\TransactionInputMethod;
use App\Enums\TransactionStatus;
use App\Models\ActivityLog;
use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\UnitItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_role_unit_and_item_relations_resolve_with_their_casts_and_scopes(): void
    {
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $permission = Permission::create(['name' => 'View dashboard', 'slug' => 'dashboard.view']);
        $role->permissions()->attach($permission);
        $unit = OperationalUnit::create([
            'code' => 'OUTLET-1',
            'name' => 'Outlet One',
            'type' => OperationalUnitType::Outlet,
            'scanner_enabled' => true,
        ]);
        $user = User::factory()->create();
        $user->role()->associate($role);
        $user->operationalUnit()->associate($unit);
        $user->save();
        $item = Item::create([
            'item_code' => 'WATER-1',
            'name' => 'Water',
            'type' => ItemType::Product,
            'created_by' => $user->id,
        ]);
        $unitItem = UnitItem::create([
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
            'selling_price' => '5000.00',
            'minimum_stock' => 10,
            'created_by' => $user->id,
        ]);
        $location = InventoryLocation::create([
            'code' => 'OUTLET-1-STOCK',
            'name' => 'Outlet One Stock',
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $unit->id,
            'created_by' => $user->id,
        ]);

        $role->refresh();
        $unit->refresh();
        $item->refresh();
        $unitItem->refresh();

        $this->assertSame($permission->id, $role->permissions->sole()->id);
        $this->assertSame($role->id, $permission->roles->sole()->id);
        $this->assertSame($user->id, $role->users->sole()->id);
        $this->assertSame($role->id, $user->role->id);
        $this->assertSame($unit->id, $user->operationalUnit->id);
        $this->assertSame($user->id, $unit->users->sole()->id);
        $this->assertSame($item->id, $unit->unitItems->sole()->item->id);
        $this->assertSame($unit->id, $unitItem->operationalUnit->id);
        $this->assertSame($user->id, $unitItem->creator->id);
        $this->assertSame($location->id, $unit->inventoryLocation->id);
        $this->assertSame($user->id, $location->creator->id);
        $this->assertSame($user->id, $item->creator->id);
        $this->assertSame($unitItem->id, $item->unitItems->sole()->id);
        $this->assertSame(OperationalUnitType::Outlet, $unit->type);
        $this->assertSame(ItemType::Product, $item->type);
        $this->assertSame(InventoryLocationType::Unit, $location->type);
        $this->assertTrue($unit->scanner_enabled);
        $this->assertTrue($unit->is_active);
        $this->assertTrue($role->is_active);
        $this->assertTrue($unitItem->is_active);
        $this->assertSame('5000.00', $unitItem->selling_price);
        $this->assertSame(10, $unitItem->minimum_stock);
        $this->assertSame(0, $item->visitor_factor);
        $this->assertSame(1, Role::active()->count());
        $this->assertSame(1, OperationalUnit::active()->ofType(OperationalUnitType::Outlet)->count());
        $this->assertSame(1, Item::active()->products()->count());
        $this->assertSame(0, Item::services()->count());
        $this->assertSame(1, UnitItem::active()->count());
        $this->assertSame(1, InventoryLocation::active()->units()->count());
        $this->assertSame(0, InventoryLocation::warehouse()->count());
    }

    public function test_inventory_and_transfer_relations_keep_balances_and_ledger_separate(): void
    {
        $user = User::factory()->create();
        $unit = OperationalUnit::create([
            'code' => 'OUTLET-1',
            'name' => 'Outlet One',
            'type' => OperationalUnitType::Outlet,
        ]);
        $item = Item::create(['item_code' => 'WATER-1', 'name' => 'Water', 'type' => ItemType::Product]);
        $warehouse = InventoryLocation::create([
            'code' => 'WH-1',
            'name' => 'Warehouse One',
            'type' => InventoryLocationType::Warehouse,
        ]);
        $outlet = InventoryLocation::create([
            'code' => 'OUTLET-1-STOCK',
            'name' => 'Outlet One Stock',
            'type' => InventoryLocationType::Unit,
            'operational_unit_id' => $unit->id,
        ]);
        $balance = InventoryBalance::create([
            'inventory_location_id' => $warehouse->id,
            'item_id' => $item->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 30,
        ]);
        $movement = StockMovement::create([
            'inventory_location_id' => $warehouse->id,
            'item_id' => $item->id,
            'direction' => StockDirection::In,
            'movement_type' => StockMovementType::StockIn,
            'quantity' => 100,
            'performed_by' => $user->id,
        ]);
        $transfer = StockTransfer::create([
            'transfer_number' => 'TRF-1',
            'source_location_id' => $warehouse->id,
            'destination_location_id' => $outlet->id,
            'status' => StockTransferStatus::Accepted,
            'created_by' => $user->id,
            'responded_by' => $user->id,
            'responded_at' => '2026-10-08 08:00:00',
        ]);
        $transferItem = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'quantity' => 30,
        ]);

        $this->assertSame($balance->id, $warehouse->inventoryBalances->sole()->id);
        $this->assertSame($warehouse->id, $balance->inventoryLocation->id);
        $this->assertSame($item->id, $balance->item->id);
        $this->assertSame(100, $balance->quantity_on_hand);
        $this->assertSame(30, $balance->quantity_reserved);
        $this->assertSame(70, $balance->available_quantity);
        $this->assertSame($movement->id, $warehouse->stockMovements->sole()->id);
        $this->assertSame($warehouse->id, $movement->inventoryLocation->id);
        $this->assertSame($item->id, $movement->item->id);
        $this->assertSame($user->id, $movement->performer->id);
        $this->assertSame(StockDirection::In, $movement->direction);
        $this->assertSame(StockMovementType::StockIn, $movement->movement_type);
        $this->assertSame(100, $movement->quantity);
        $this->assertNotNull($movement->created_at);
        $this->assertArrayNotHasKey('updated_at', $movement->getAttributes());
        $this->assertSame($transfer->id, $warehouse->outgoingTransfers->sole()->id);
        $this->assertSame($transfer->id, $outlet->incomingTransfers->sole()->id);
        $this->assertSame($warehouse->id, $transfer->sourceLocation->id);
        $this->assertSame($outlet->id, $transfer->destinationLocation->id);
        $this->assertSame($user->id, $transfer->creator->id);
        $this->assertSame($user->id, $transfer->responder->id);
        $this->assertSame($transferItem->id, $transfer->transferItems->sole()->id);
        $this->assertSame($item->id, $transferItem->item->id);
        $this->assertSame(30, $transferItem->quantity);
        $this->assertSame(StockTransferStatus::Accepted, $transfer->status);
        $this->assertFalse($transfer->isPending());
        $this->assertTrue($transfer->isAccepted());
        $this->assertFalse($transfer->isRejected());
        $this->assertNotNull($transfer->responded_at);
        $this->assertSame(1, InventoryLocation::warehouse()->count());
        $this->assertSame($balance->id, $item->inventoryBalances->sole()->id);
        $this->assertSame($movement->id, $item->stockMovements->sole()->id);
        $this->assertSame($transferItem->id, $item->stockTransferItems->sole()->id);
        $this->assertSame($movement->id, $user->performedStockMovements->sole()->id);
        $this->assertSame($transfer->id, $user->createdStockTransfers->sole()->id);
        $this->assertSame($transfer->id, $user->respondedStockTransfers->sole()->id);
    }

    public function test_transaction_and_activity_relations_preserve_price_snapshot_and_metadata(): void
    {
        $user = User::factory()->create();
        $unit = OperationalUnit::create([
            'code' => 'TICKETING',
            'name' => 'Ticketing',
            'type' => OperationalUnitType::Ticketing,
        ]);
        $item = Item::create([
            'item_code' => 'TICKET',
            'name' => 'Ticket',
            'type' => ItemType::Service,
            'visitor_factor' => 1,
        ]);
        $transaction = Transaction::create([
            'transaction_number' => 'TRX-1',
            'operational_unit_id' => $unit->id,
            'status' => TransactionStatus::Paid,
            'created_by' => $user->id,
            'paid_by' => $user->id,
            'subtotal' => '20000.00',
            'total' => '20000.00',
            'visitor_count' => 4,
            'paid_at' => '2026-10-08 09:00:00',
        ]);
        $transactionItem = TransactionItem::create([
            'transaction_id' => $transaction->id,
            'item_id' => $item->id,
            'quantity' => 4,
            'unit_price' => '5000.00',
            'subtotal' => '20000.00',
            'input_method' => TransactionInputMethod::Manual,
        ]);
        $log = ActivityLog::create([
            'actor_id' => $user->id,
            'action' => 'TRANSACTION_PAID',
            'subject_type' => 'transaction',
            'subject_id' => $transaction->id,
            'metadata' => ['transaction_number' => 'TRX-1'],
        ]);

        $this->assertSame($transaction->id, $unit->transactions->sole()->id);
        $this->assertSame($unit->id, $transaction->operationalUnit->id);
        $this->assertSame($transactionItem->id, $transaction->transactionItems->sole()->id);
        $this->assertSame($transaction->id, $transactionItem->transaction->id);
        $this->assertSame($item->id, $transactionItem->item->id);
        $this->assertSame($transactionItem->id, $item->transactionItems->sole()->id);
        $this->assertSame(TransactionStatus::Paid, $transaction->status);
        $this->assertTrue($transaction->isPaid());
        $this->assertFalse($transaction->isCart());
        $this->assertSame('20000.00', $transaction->subtotal);
        $this->assertSame('20000.00', $transaction->total);
        $this->assertSame(4, $transaction->visitor_count);
        $this->assertNotNull($transaction->paid_at);
        $this->assertSame(4, $transactionItem->quantity);
        $this->assertSame('5000.00', $transactionItem->unit_price);
        $this->assertSame('20000.00', $transactionItem->subtotal);
        $this->assertSame(TransactionInputMethod::Manual, $transactionItem->input_method);
        $this->assertSame($user->id, $transaction->creator->id);
        $this->assertSame($user->id, $transaction->payer->id);
        $this->assertSame($transaction->id, $user->createdTransactions->sole()->id);
        $this->assertSame($transaction->id, $user->paidTransactions->sole()->id);
        $this->assertSame($log->id, $user->activityLogs->sole()->id);
        $this->assertSame($user->id, $log->actor->id);
        $this->assertSame(['transaction_number' => 'TRX-1'], $log->metadata);
        $this->assertNotNull($log->created_at);
        $this->assertArrayNotHasKey('updated_at', $log->getAttributes());
        $this->assertSame(1, Item::services()->count());
    }

    public function test_read_only_status_helpers_identify_the_other_states(): void
    {
        $pending = new StockTransfer(['status' => StockTransferStatus::Pending]);
        $rejected = new StockTransfer(['status' => StockTransferStatus::Rejected]);
        $cart = new Transaction(['status' => TransactionStatus::Cart]);

        $this->assertTrue($pending->isPending());
        $this->assertTrue($rejected->isRejected());
        $this->assertTrue($cart->isCart());
        $this->assertFalse($cart->isPaid());
    }
}
