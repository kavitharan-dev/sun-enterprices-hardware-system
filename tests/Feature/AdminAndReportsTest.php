<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\InAppAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user_with_one_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Cashier',
                'email' => 'newcashier@hardware.local',
                'phone' => '0771234567',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'cashier',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'newcashier@hardware.local')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('cashier'));
        $this->assertTrue($created->is_active);
    }

    public function test_store_manager_cannot_manage_users(): void
    {
        $store = $this->userWithRole('store_manager');

        $this->actingAs($store)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $this->admin();
        $cashier = $this->userWithRole('cashier');
        $cashier->update(['is_active' => false]);

        $this->post('/login', [
            'email' => $cashier->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_can_update_company_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'company_name' => 'Sun Hardware',
                'company_address' => 'Colombo',
                'company_phone' => '0112223333',
                'company_email' => 'info@example.com',
                'currency' => 'Rs.',
                'currency_code' => 'LKR',
                'invoice_prefix' => 'INV',
                'purchase_prefix' => 'PO',
                'material_request_prefix' => 'MR',
                'material_issue_prefix' => 'MI',
                'project_prefix' => 'PRJ',
                'worker_prefix' => 'WRK',
                'timezone' => 'Asia/Colombo',
            ])
            ->assertRedirect();

        $this->assertSame('Sun Hardware', Setting::get('company_name'));
    }

    public function test_admin_can_view_activity_logs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertSee('Activity logs');
    }

    public function test_admin_and_store_manager_can_open_sales_report(): void
    {
        $admin = $this->admin();
        $store = $this->userWithRole('store_manager');

        $this->actingAs($admin)->get(route('reports.sales'))->assertOk()->assertSee('Sales report');
        $this->actingAs($store)->get(route('reports.sales'))->assertOk();
    }

    public function test_site_manager_can_open_project_reports_but_not_sales(): void
    {
        $site = $this->userWithRole('site_manager');

        $this->actingAs($site)->get(route('reports.projects'))->assertOk();
        $this->actingAs($site)->get(route('reports.sales'))->assertForbidden();
    }

    public function test_cashier_can_access_reports(): void
    {
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)->get(route('reports.index'))->assertOk();
        $this->actingAs($cashier)->get(route('reports.sales'))->assertOk();
    }

    public function test_user_can_open_notification_inbox(): void
    {
        $admin = $this->admin();
        $admin->notify(new InAppAlert('Test alert', 'Something happened', 'test', route('dashboard')));

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Test alert');
    }

    public function test_sales_report_csv_export_downloads(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('reports.sales', ['export' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('store_manager');
        Role::findOrCreate('cashier');
        Role::findOrCreate('site_manager');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
