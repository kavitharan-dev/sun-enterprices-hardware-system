<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectPagesSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_create_ok_even_if_site_manager_role_is_missing(): void
    {
        Role::findOrCreate('admin');
        // Intentionally do NOT create site_manager role.

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Customer::query()->create(['name' => 'Owner Co', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('construction.projects.create'))
            ->assertOk()
            ->assertSee('New Project');
    }

    public function test_ensure_roles_seeder_creates_all_roles(): void
    {
        $this->seed(\Database\Seeders\EnsureRolesSeeder::class);

        foreach (['admin', 'store_manager', 'cashier', 'site_manager'] as $role) {
            $this->assertTrue(Role::query()->where('name', $role)->exists(), "Missing role {$role}");
        }
    }
}
