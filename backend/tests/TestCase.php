<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsSystemUser(string $roleSlug = 'superadmin'): User
    {
        $this->seed(DatabaseSeeder::class);

        if ($roleSlug === 'superadmin') {
            $user = User::where('email', 'admin@pandansari.local')->firstOrFail();
        } else {
            $user = User::factory()->create();
            $user->forceFill(['role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id])->save();
        }
        $user->refresh();
        $this->actingAs($user, 'web');

        return $user;
    }
}
