<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class UserDomainSafetyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_existing_factory_and_password_cast_remain_compatible_with_new_user_fields(): void
    {
        $user = User::factory()->create();
        $user->refresh();

        $this->assertInstanceOf(Authenticatable::class, $user);
        $this->assertContains(HasApiTokens::class, class_uses_recursive(User::class));
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertTrue($user->is_active);
        $this->assertNull($user->role_id);
        $this->assertNull($user->operational_unit_id);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertSame(1, User::active()->count());
    }

    public function test_role_and_unit_cannot_be_set_through_user_mass_assignment(): void
    {
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $user = User::factory()->create();

        $this->assertSame(['name', 'email', 'password'], $user->getFillable());
        $this->assertFalse($user->isFillable('role_id'));
        $this->assertFalse($user->isFillable('operational_unit_id'));
        $this->assertFalse($user->isFillable('is_active'));
        $this->assertNull($user->role_id);

        $user->role()->associate($role);
        $user->save();

        $this->assertSame($role->id, $user->fresh()->role->id);
    }
}
