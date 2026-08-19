<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HealthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('store.products.index'))->assertRedirect(route('login'));
        $this->get(route('construction.projects.index'))->assertRedirect(route('login'));
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_site_manager_cannot_open_store_inventory(): void
    {
        Role::findOrCreate('site_manager');
        $site = User::factory()->create();
        $site->assignRole('site_manager');

        $this->actingAs($site)
            ->get(route('store.inventory.index'))
            ->assertForbidden();
    }

    public function test_store_manager_cannot_open_admin_settings(): void
    {
        Role::findOrCreate('store_manager');
        $store = User::factory()->create();
        $store->assignRole('store_manager');

        $this->actingAs($store)
            ->get(route('admin.settings.edit'))
            ->assertForbidden();
    }
}
