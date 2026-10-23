<?php

namespace Tests\Feature;

use App\Enums\OperationalUnitType;
use App\Models\OperationalUnit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperationalUnitCodeMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_normalizes_legacy_codes_without_changing_valid_codes(): void
    {
        OperationalUnit::create(['code' => 'OU0001', 'name' => 'Existing Outlet', 'type' => OperationalUnitType::Outlet]);
        OperationalUnit::create(['code' => 'OUT-JAJAN', 'name' => 'Outlet 1', 'type' => OperationalUnitType::Outlet]);
        OperationalUnit::create(['code' => 'OUT-MINUMAN', 'name' => 'Outlet 2', 'type' => OperationalUnitType::Outlet]);
        OperationalUnit::create(['code' => 'OUT-POP-MIE', 'name' => 'Outlet 3', 'type' => OperationalUnitType::Outlet]);
        OperationalUnit::create(['code' => 'TKT-DEMO', 'name' => 'Loket Ticket', 'type' => OperationalUnitType::Ticketing]);

        $migration = require database_path('migrations/2026_10_23_180000_normalize_operational_unit_codes.php');
        $migration->up();

        $this->assertDatabaseHas('operational_units', ['name' => 'Existing Outlet', 'code' => 'OU0001']);
        $this->assertDatabaseHas('operational_units', ['name' => 'Outlet 1', 'code' => 'OU0002']);
        $this->assertDatabaseHas('operational_units', ['name' => 'Outlet 2', 'code' => 'OU0003']);
        $this->assertDatabaseHas('operational_units', ['name' => 'Outlet 3', 'code' => 'OU0004']);
        $this->assertDatabaseHas('operational_units', ['name' => 'Loket Ticket', 'code' => 'LO0001']);
        $this->assertSame(5, OperationalUnit::distinct()->count('code'));
    }
}
