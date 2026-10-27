<?php

namespace Database\Seeders;

use App\Enums\InventoryLocationType;
use App\Enums\ItemType;
use App\Enums\OperationalUnitType;
use App\Models\InventoryBalance;
use App\Models\InventoryLocation;
use App\Models\Item;
use App\Models\OperationalUnit;
use App\Models\UnitItem;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\StockTransferService;
use App\Services\WarehouseStockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private const int DEFAULT_INITIAL_STOCK = 30;

    private const int DEFAULT_MINIMUM_STOCK = 5;

    private const string OUTLET_ONE_NAME = 'Outlet 1';

    /**
     * Closing stock targets for Outlet 1.
     *
     * @var array<string, int>
     */
    private const array OUTLET_ONE_STOCK_TARGETS = [
        'AQUA 1,5 L' => 30,
        'AQUA 600ML' => 6,
        'BASO' => 4,
        'BENG-BENG' => 0,
        'BETTER' => 25,
        'CHITATO' => 6,
        'FRESH TEA 300ML' => 3,
        'PIATOS' => 0,
        'TEH PUCUK HARUM' => 20,
    ];

    /** @var array<int, string> */
    private const array OBSOLETE_DEMO_UNIT_CODES = [
        'OUT-BASO',
        'TKT-PANDANSARI',
    ];

    /**
     * Catalog extracted from the attached historical sales workbook.
     *
     * A product is stored once in the item master. Repeating an SKU in another
     * outlet below will reuse the same item and create a separate unit item.
     *
     * @var array<int, array{name: string, items: array<int, array{name: string, price: string}>}>
     */
    private const array OUTLET_CATALOG = [
        [
            'name' => 'Outlet 1',
            'items' => [
                ['name' => 'AQUA 1,5 L', 'price' => '7000.00'],
                ['name' => 'AQUA 600ML', 'price' => '5000.00'],
                ['name' => 'BASO', 'price' => '10000.00'],
                ['name' => 'BENG-BENG', 'price' => '2500.00'],
                ['name' => 'BETTER', 'price' => '10000.00'],
                ['name' => 'CHITATO', 'price' => '4000.00'],
                ['name' => 'FRESH TEA 300ML', 'price' => '3000.00'],
                ['name' => 'PIATOS', 'price' => '3500.00'],
                ['name' => 'TEH PUCUK HARUM', 'price' => '4000.00'],
            ],
        ],
        [
            'name' => 'Outlet 2',
            'items' => [
                ['name' => 'BENG-BENG DRINK', 'price' => '5000.00'],
                ['name' => 'CHOCOLATOS DRINK', 'price' => '5000.00'],
                ['name' => 'ES JERUK', 'price' => '5000.00'],
                ['name' => 'ES TEH', 'price' => '5000.00'],
                ['name' => 'GOOD DAY CAPPUCINO', 'price' => '5000.00'],
                ['name' => 'GOOD DAY FREEZE', 'price' => '5000.00'],
                ['name' => 'JERUK HANGAT', 'price' => '5000.00'],
                ['name' => 'NUTRISARI ANGGUR', 'price' => '5000.00'],
                ['name' => 'NUTRISARI JERUK', 'price' => '5000.00'],
                ['name' => 'TEH HANGAT', 'price' => '5000.00'],
            ],
        ],
        [
            'name' => 'Outlet 3',
            'items' => [
                ['name' => 'ICE CREAM CONTONG', 'price' => '5000.00'],
                ['name' => 'ICE CREAM CUP', 'price' => '5000.00'],
                ['name' => 'INDOMIE GORENG', 'price' => '8000.00'],
                ['name' => 'INDOMIE GORENG GEPREK', 'price' => '8000.00'],
                ['name' => 'INDOMIE GORENG KRIUK', 'price' => '8000.00'],
                ['name' => 'INDOMIE GORENG RENDANG', 'price' => '8000.00'],
                ['name' => 'POP MIE AYAM', 'price' => '10000.00'],
                ['name' => 'POP MIE BASO', 'price' => '10000.00'],
                ['name' => 'POP MIE KARE', 'price' => '10000.00'],
                ['name' => 'POP MIE SOTO', 'price' => '10000.00'],
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(
        StockTransferService $stockTransfers,
        WarehouseStockService $warehouseStock,
        UserSeeder $userSeeder,
    ): void {
        $userSeeder->validateDemoAccountConfiguration();

        $this->call(DatabaseSeeder::class);

        $superadmin = User::where('email', config('auth.initial_superadmin_email'))->firstOrFail();
        $outlets = [];
        $products = [];
        $outletOneStockTargets = [];

        foreach (self::OUTLET_CATALOG as $outletData) {
            $outlet = $this->upsertUnit(
                $outletData['name'],
                OperationalUnitType::Outlet,
                $superadmin,
            );
            $outlets[] = $outlet;
            $outletItemIds = [];

            foreach ($outletData['items'] as $itemData) {
                $item = $this->upsertItem(
                    $this->productCode($itemData['name']),
                    null,
                    $itemData['name'],
                    ItemType::Product,
                    'pcs',
                    0,
                    $superadmin,
                );
                $products[$item->id] = $item;
                $outletItemIds[] = $item->id;

                if ($outlet->name === self::OUTLET_ONE_NAME) {
                    $outletOneStockTargets[$item->id] = self::OUTLET_ONE_STOCK_TARGETS[$item->name];
                }

                $this->upsertUnitItem(
                    $outlet,
                    $item,
                    $itemData['price'],
                    self::DEFAULT_MINIMUM_STOCK,
                    $superadmin,
                );
            }

            $this->removeUnexpectedUnitItems($outlet, $outletItemIds);
        }

        $ticketing = $this->upsertUnit(
            'Loket Ticket',
            OperationalUnitType::Ticketing,
            $superadmin,
        );
        $ticket = $this->upsertItem(
            'SERVICE-TICKET',
            null,
            'Tiket Masuk',
            ItemType::Service,
            'tiket',
            1,
            $superadmin,
        );
        $this->upsertUnitItem($ticketing, $ticket, '15000.00', 0, $superadmin);
        $this->removeUnexpectedUnitItems($ticketing, [$ticket->id]);

        $demoAccounts = $userSeeder->seedDemoAccounts($outlets, $ticketing);
        $warehouseUser = $demoAccounts['warehouse'];

        $this->removeObsoleteDemoUnits();

        $outletOneStockShortfalls = $this->outletStockShortfalls($outlets[0], $outletOneStockTargets);

        foreach ($products as $product) {
            $this->topUpWarehouse(
                $warehouseStock,
                $warehouseUser,
                $product,
                self::DEFAULT_INITIAL_STOCK + ($outletOneStockShortfalls[$product->id] ?? 0),
            );
        }

        $this->seedOutletInventory(
            $stockTransfers,
            $warehouseUser,
            $demoAccounts['outlets'][0],
            $outlets[0],
            $outletOneStockTargets,
        );
    }

    private function productCode(string $name): string
    {
        return 'PROD-'.Str::upper(Str::slug($name));
    }

    /**
     * @param  array<int, int>  $expectedItemIds
     */
    private function removeUnexpectedUnitItems(OperationalUnit $unit, array $expectedItemIds): void
    {
        $unit->unitItems()
            ->whereNotIn('item_id', $expectedItemIds)
            ->delete();
    }

    private function removeObsoleteDemoUnits(): void
    {
        $obsoleteUnits = OperationalUnit::query()
            ->whereIn('code', self::OBSOLETE_DEMO_UNIT_CODES)
            ->with('inventoryLocation')
            ->get();

        foreach ($obsoleteUnits as $unit) {
            if ($this->hasOperationalHistory($unit)) {
                $this->archiveUnit($unit);

                continue;
            }

            DB::transaction(function () use ($unit): void {
                UserRoleAssignment::where('operational_unit_id', $unit->id)->delete();
                $unit->unitItems()->delete();
                $unit->inventoryLocation?->delete();
                $unit->delete();
            });
        }
    }

    private function hasOperationalHistory(OperationalUnit $unit): bool
    {
        if ($unit->transactions()->exists() || $unit->users()->exists()) {
            return true;
        }

        $location = $unit->inventoryLocation;

        return $location !== null && (
            $location->inventoryBalances()->exists()
            || $location->stockMovements()->exists()
            || $location->outgoingTransfers()->exists()
            || $location->incomingTransfers()->exists()
        );
    }

    private function archiveUnit(OperationalUnit $unit): void
    {
        $unit->forceFill([
            'scanner_enabled' => false,
            'is_active' => false,
        ])->save();
        $unit->inventoryLocation()->update(['is_active' => false]);
        $unit->unitItems()->update(['is_active' => false]);
        UserRoleAssignment::where('operational_unit_id', $unit->id)
            ->update(['is_active' => false]);
    }

    private function upsertUnit(
        string $name,
        OperationalUnitType $type,
        User $creator,
    ): OperationalUnit {
        $unit = OperationalUnit::firstOrNew([
            'name' => $name,
            'type' => $type,
        ]);

        if (! $unit->exists || preg_match('/^[A-Z]{2}\d{4}$/', $unit->code) !== 1) {
            $unit->code = $this->availableUnitCode($name);
        }

        $unit->fill([
            'scanner_enabled' => true,
            'is_active' => true,
            'created_by' => $creator->id,
        ])->save();

        InventoryLocation::updateOrCreate(['operational_unit_id' => $unit->id], [
            'code' => 'UNIT-'.$unit->id,
            'name' => $unit->name,
            'type' => InventoryLocationType::Unit,
            'is_active' => true,
            'created_by' => $creator->id,
        ]);

        return $unit;
    }

    private function availableUnitCode(string $name): string
    {
        $letters = preg_replace('/[^A-Z]/', '', Str::upper(Str::ascii($name))) ?? '';
        $prefix = substr($letters.'XX', 0, 2);

        for ($offset = 1; $offset <= 10000; $offset++) {
            $code = sprintf('%s%04d', $prefix, $offset % 10000);

            if (! OperationalUnit::where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new LogicException("No operational unit codes remain for prefix {$prefix}.");
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

    /**
     * @param  array<int, int>  $stockTargets
     * @return array<int, int>
     */
    private function outletStockShortfalls(OperationalUnit $outlet, array $stockTargets): array
    {
        $location = $outlet->inventoryLocation()->firstOrFail();
        $currentQuantities = InventoryBalance::query()
            ->where('inventory_location_id', $location->id)
            ->whereIn('item_id', array_keys($stockTargets))
            ->pluck('quantity_on_hand', 'item_id');
        $shortfalls = [];

        foreach ($stockTargets as $itemId => $targetQuantity) {
            $currentQuantity = (int) ($currentQuantities->get($itemId) ?? 0);

            if ($targetQuantity > $currentQuantity) {
                $shortfalls[$itemId] = $targetQuantity - $currentQuantity;
            }
        }

        return $shortfalls;
    }

    /**
     * @param  array<int, int>  $stockTargets
     */
    private function seedOutletInventory(
        StockTransferService $stockTransfers,
        User $warehouseUser,
        User $outletUser,
        OperationalUnit $outlet,
        array $stockTargets,
    ): void {
        $location = $outlet->inventoryLocation()->firstOrFail();
        $itemIds = array_keys($stockTargets);
        $currentQuantities = InventoryBalance::query()
            ->where('inventory_location_id', $location->id)
            ->whereIn('item_id', $itemIds)
            ->pluck('quantity_on_hand', 'item_id');

        foreach ($stockTargets as $itemId => $targetQuantity) {
            if ($targetQuantity === 0) {
                InventoryBalance::firstOrCreate([
                    'inventory_location_id' => $location->id,
                    'item_id' => $itemId,
                ], [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]);
            }
        }

        $items = [];
        foreach ($stockTargets as $itemId => $targetQuantity) {
            $currentQuantity = (int) ($currentQuantities->get($itemId) ?? 0);

            if ($targetQuantity > $currentQuantity) {
                $items[] = [
                    'item_id' => $itemId,
                    'quantity' => $targetQuantity - $currentQuantity,
                ];
            }
        }

        if ($items === []) {
            return;
        }

        $transfer = $stockTransfers->create($warehouseUser, $outlet->id, $items);
        $stockTransfers->accept($outletUser, $transfer);
    }
}
