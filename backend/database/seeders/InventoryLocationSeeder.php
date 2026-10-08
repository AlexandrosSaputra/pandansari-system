<?php

namespace Database\Seeders;

use App\Enums\InventoryLocationType;
use App\Models\InventoryLocation;
use Illuminate\Database\Seeder;

class InventoryLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        InventoryLocation::updateOrCreate(
            ['code' => 'WH-MAIN'],
            [
                'name' => 'Gudang Utama',
                'type' => InventoryLocationType::Warehouse,
                'operational_unit_id' => null,
                'is_active' => true,
                'created_by' => null,
            ],
        );
    }
}
