<?php

namespace Database\Seeders;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\UnitItem;
use App\Models\User;
use App\Services\WarehouseStockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(WarehouseStockService $warehouseStock): void
    {
        $password = (string) config('demo.user_password');

        if ($password === '') {
            throw new LogicException('DEMO_USER_PASSWORD must be configured before seeding demo data.');
        }

        $this->call(DatabaseSeeder::class);

        $superadmin = User::where('email', config('auth.initial_superadmin_email'))->firstOrFail();
        $warehouseRole = Role::where('slug', 'warehouse')->firstOrFail();
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $outlet = $this->upsertUnit('OUT-DEMO', 'Outlet Demo Pandansari', OperationalUnitType::Outlet, $superadmin);
        $ticketing = $this->upsertUnit('TKT-DEMO', 'Loket Tiket Demo Pandansari', OperationalUnitType::Ticketing, $superadmin);

        $water = $this->upsertItem(
            'PROD-WATER',
            '8990000000001',
            'Air Mineral Demo',
            ItemType::Product,
            'botol',
            0,
            $superadmin,
        );
        $snack = $this->upsertItem(
            'PROD-SNACK',
            '8990000000002',
            'Makanan Ringan Demo',
            ItemType::Product,
            'bungkus',
            0,
            $superadmin,
        );
        $ticket = $this->upsertItem(
            'SERVICE-TICKET',
            null,
            'Tiket Masuk Demo',
            ItemType::Service,
            'tiket',
            1,
            $superadmin,
        );

        $this->upsertUnitItem($outlet, $water, '8000.00', 5, $superadmin);
        $this->upsertUnitItem($outlet, $snack, '12000.00', 3, $superadmin);
        $this->upsertUnitItem($ticketing, $water, '10000.00', 5, $superadmin);
        $this->upsertUnitItem($ticketing, $ticket, '15000.00', 0, $superadmin);

        $warehouseUser = $this->upsertUser(
            'warehouse.demo@pandansari.local',
            'warehouse-demo',
            'Petugas Gudang Demo',
            $warehouseRole,
            null,
            $password,
        );
        $adminUser = $this->upsertUser(
            'admin.demo@pandansari.local',
            'admin-demo',
            'Petugas Outlet dan Ticketing Demo',
            $adminRole,
            $outlet,
            $password,
        );
        $adminUser->assignments()->updateOrCreate([
            'role_id' => $adminRole->id,
            'operational_unit_id' => $ticketing->id,
        ], ['is_active' => true]);

        $this->topUpWarehouse($warehouseStock, $warehouseUser, $water, 30);
        $this->topUpWarehouse($warehouseStock, $warehouseUser, $snack, 8);
    }

    private function upsertUnit(
        string $code,
        string $name,
        OperationalUnitType $type,
        User $creator,
    ): OperationalUnit {
        $unit = OperationalUnit::updateOrCreate(['code' => $code], [
            'name' => $name,
            'type' => $type,
            'scanner_enabled' => true,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        InventoryLocation::updateOrCreate(['operational_unit_id' => $unit->id], [
            'code' => 'UNIT-'.$unit->id,
            'name' => $unit->name,
            'type' => InventoryLocationType::Unit,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        return $unit;
    }

    private function upsertItem(
        string $code,
        ?string $barcode,
        string $name,
        ItemType $type,
        string $uom,
        int $visitorFactor,
        User $creator,
    ): Item {
        return Item::updateOrCreate(['item_code' => $code], [
            'barcode' => $barcode,
            'name' => $name,
            'type' => $type,
            'uom' => $uom,
            'visitor_factor' => $visitorFactor,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);
    }

    private function upsertUnitItem(
        OperationalUnit $unit,
        Item $item,
        string $sellingPrice,
        int $minimumStock,
        User $creator,
    ): void {
        UnitItem::updateOrCreate([
            'operational_unit_id' => $unit->id,
            'item_id' => $item->id,
        ], [
            'selling_price' => $sellingPrice,
            'minimum_stock' => $minimumStock,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);
    }

    private function upsertUser(
        string $email,
        string $username,
        string $name,
        Role $role,
        ?OperationalUnit $unit,
        string $password,
    ): User {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'username' => $username,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->forceFill([
            'name' => $name,
            'username' => $username,
            'role_id' => $role->id,
            'operational_unit_id' => $unit?->id,
            'is_active' => true,
        ]);
        if ($user->isDirty()) {
            $user->save();
        }

        $user->assignments()->updateOrCreate([
            'role_id' => $role->id,
            'operational_unit_id' => $unit?->id,
        ], ['is_active' => true]);

        return $user;
    }

    private function topUpWarehouse(
        WarehouseStockService $warehouseStock,
        User $warehouseUser,
        Item $item,
        int $targetQuantity,
    ): void {
        $warehouse = $warehouseStock->mainWarehouse();
        $currentQuantity = (int) (InventoryBalance::query()
            ->where('inventory_location_id', $warehouse->id)
            ->where('item_id', $item->id)
            ->value('quantity_on_hand') ?? 0);

        if ($currentQuantity < $targetQuantity) {
            $warehouseStock->stockIn(
                $warehouseUser,
                $item->id,
                $targetQuantity - $currentQuantity,
                'Persediaan awal demo',
            );
        }
    }
}
