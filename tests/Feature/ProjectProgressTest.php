<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\DailyProgress;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_manager_can_log_progress_on_assigned_project(): void
    {
        [, $site] = $this->seedRoles();
        $project = $this->project($site);

        $this->actingAs($site)
            ->post(route('construction.projects.progress.store', $project), [
                'progress_date' => now()->toDateString(),
                'work_completed' => 'Foundation completed',
                'workers_present' => 6,
                'progress_percentage' => 25,
                'notes' => 'On schedule',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('daily_progress', [
            'project_id' => $project->id,
            'work_completed' => 'Foundation completed',
            'workers_present' => 6,
        ]);

        $this->assertSame(25.0, (float) $project->fresh()->progress_percentage);
    }

    public function test_site_manager_cannot_log_progress_on_unassigned_project(): void
    {
        [, $site, $other] = $this->seedRoles();
        $project = $this->project($other);

        $this->actingAs($site)
            ->post(route('construction.projects.progress.store', $project), [
                'progress_date' => now()->toDateString(),
                'work_completed' => 'Should fail',
                'workers_present' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, DailyProgress::query()->count());
    }

    public function test_duplicate_progress_date_is_blocked(): void
    {
        [, $site] = $this->seedRoles();
        $project = $this->project($site);

        $payload = [
            'progress_date' => now()->toDateString(),
            'work_completed' => 'Day one',
            'workers_present' => 4,
            'progress_percentage' => 10,
        ];

        $this->actingAs($site)
            ->post(route('construction.projects.progress.store', $project), $payload)
            ->assertRedirect();

        $this->actingAs($site)
            ->from(route('construction.projects.show', $project))
            ->post(route('construction.projects.progress.store', $project), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('progress_date');

        $this->assertSame(1, DailyProgress::query()->count());
    }

    public function test_manual_expense_increases_spent_and_cannot_use_material_category(): void
    {
        [, $site] = $this->seedRoles();
        $project = $this->project($site);

        $this->actingAs($site)
            ->post(route('construction.projects.expenses.store', $project), [
                'category' => ExpenseCategory::Labour->value,
                'amount' => 15000,
                'expense_date' => now()->toDateString(),
                'description' => 'Mason wages',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(15000.0, $project->fresh()->totalSpent());

        $this->actingAs($site)
            ->from(route('construction.projects.show', $project))
            ->post(route('construction.projects.expenses.store', $project), [
                'category' => ExpenseCategory::Material->value,
                'amount' => 500,
                'expense_date' => now()->toDateString(),
                'description' => 'Should not post',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('category');
    }

    public function test_cannot_delete_automatic_material_expense(): void
    {
        [$admin, $site] = $this->seedRoles();
        $project = $this->project($site);

        $expense = ProjectExpense::query()->create([
            'project_id' => $project->id,
            'category' => ExpenseCategory::Material,
            'amount' => 21500,
            'expense_date' => now()->toDateString(),
            'description' => 'Material issue MI-1',
            'reference_type' => 'App\\Models\\MaterialIssue',
            'reference_id' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($site)
            ->from(route('construction.projects.show', $project))
            ->delete(route('construction.projects.expenses.destroy', [$project, $expense]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($expense);
    }

    public function test_site_manager_can_view_assigned_project_dashboard(): void
    {
        [, $site] = $this->seedRoles();
        $project = $this->project($site);

        $this->actingAs($site)
            ->get(route('construction.projects.dashboard', $project))
            ->assertOk()
            ->assertSee('dashboard')
            ->assertSee('Materials received');
    }

    public function test_cashier_cannot_access_progress_or_dashboard(): void
    {
        Role::findOrCreate('cashier');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        [, $site] = $this->seedRoles();
        $project = $this->project($site);

        $this->actingAs($cashier)
            ->get(route('construction.projects.dashboard', $project))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->post(route('construction.projects.progress.store', $project), [
                'progress_date' => now()->toDateString(),
                'work_completed' => 'Nope',
                'workers_present' => 1,
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function seedRoles(): array
    {
        Role::findOrCreate('admin');
        Role::findOrCreate('site_manager');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $site = User::factory()->create();
        $site->assignRole('site_manager');

        $other = User::factory()->create();
        $other->assignRole('site_manager');

        return [$admin, $site, $other];
    }

    private function project(User $site): Project
    {
        $admin = User::query()->role('admin')->first() ?? User::factory()->create();

        return Project::query()->create([
            'project_code' => 'PRJ-2026-0500',
            'name' => 'Kumar Residence',
            'customer_id' => Customer::query()->create(['name' => 'Kumar'])->id,
            'location' => 'Nugegoda',
            'budget' => 100000,
            'start_date' => now()->toDateString(),
            'status' => ProjectStatus::Active,
            'progress_percentage' => 0,
            'site_manager_id' => $site->id,
            'created_by' => $admin->id,
        ]);
    }
}
