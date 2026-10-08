<?php

namespace Tests\Feature;

use App\Enums\OperationalUnitType;
use App\Models\OperationalUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_permission_middleware_returns_json_401_for_guests_and_403_without_permission(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->registerPermissionRoute('inventory.stock-in');

        $this->getJson('/api/_test/permission')->assertUnauthorized();

        $admin = $this->account('admin', withUnit: true);
        $this->actingAs($admin, 'web')
            ->getJson('/api/_test/permission')
            ->assertForbidden()
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_admin_warehouse_and_superadmin_authorize_from_permission_slugs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->registerPermissionRoute('dashboard.view');

        $admin = $this->account('admin', withUnit: true);
        $warehouse = $this->account('warehouse');
        $superadmin = $this->account('superadmin');

        foreach ([$admin, $warehouse, $superadmin] as $user) {
            $this->actingAs($user, 'web')
                ->getJson('/api/_test/permission')
                ->assertOk()
                ->assertExactJson(['allowed' => true]);
        }

        $this->assertFalse($admin->hasPermission('inventory.stock-in'));
        $this->assertTrue($warehouse->hasPermission('inventory.stock-in'));
        $this->assertTrue($superadmin->hasPermission('rbac.manage'));
    }

    public function test_permission_helper_denies_missing_or_inactive_role_and_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->account('admin', withUnit: true);

        $this->assertTrue($user->hasPermission('transaction.create'));

        $user->role->update(['is_active' => false]);
        $user->unsetRelation('role');
        $this->assertFalse($user->hasPermission('transaction.create'));

        $user->role->update(['is_active' => true]);
        $user->forceFill(['role_id' => null])->save();
        $user->unsetRelation('role');
        $this->assertFalse($user->hasPermission('transaction.create'));

        $user->forceFill(['role_id' => Role::where('slug', 'admin')->value('id'), 'is_active' => false])->save();
        $user->unsetRelation('role');
        $this->assertFalse($user->hasPermission('transaction.create'));
    }

    public function test_permission_middleware_rejects_account_deactivated_after_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->registerPermissionRoute('dashboard.view');
        $unit = OperationalUnit::create([
            'code' => 'OUT-A',
            'name' => 'Outlet A',
            'type' => OperationalUnitType::Outlet,
        ]);
        $admin = $this->account('admin', unit: $unit);
        $unit->update(['is_active' => false]);

        $this->actingAs($admin, 'web')
            ->getJson('/api/_test/permission')
            ->assertForbidden();
    }

    private function registerPermissionRoute(string $permission): void
    {
        Route::middleware(['api', 'auth:sanctum', 'permission:'.$permission])
            ->get('/api/_test/permission', fn () => response()->json(['allowed' => true]));
    }

    private function account(string $slug, bool $withUnit = false, ?OperationalUnit $unit = null): User
    {
        if ($withUnit) {
            $unit = OperationalUnit::create([
                'code' => 'OUT-A',
                'name' => 'Outlet A',
                'type' => OperationalUnitType::Outlet,
            ]);
        }

        $user = User::factory()->create();
        $user->forceFill([
            'role_id' => Role::where('slug', $slug)->value('id'),
            'operational_unit_id' => $unit?->id,
        ])->save();

        return $user->refresh();
    }
}
