<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_add_worker_page(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('construction.workers.create'))
            ->assertOk()
            ->assertSee('Add Worker');
    }

    public function test_site_manager_can_add_worker(): void
    {
        Role::findOrCreate('site_manager');
        $user = User::factory()->create();
        $user->assignRole('site_manager');

        $this->actingAs($user)
            ->get(route('construction.workers.create'))
            ->assertOk();

        $response = $this->actingAs($user)->post(route('construction.workers.store'), [
            'name' => 'Ravi Mason',
            'job_role' => 'Mason',
            'daily_rate' => 2500,
            'weekly_salary' => 15000,
            'phone' => '0771234567',
            'nic' => '901234567V',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $response->assertRedirect(route('construction.workers.index'));
        $this->assertDatabaseHas('workers', ['name' => 'Ravi Mason']);
        $this->assertNotNull(Worker::query()->where('name', 'Ravi Mason')->value('worker_code'));
    }

    public function test_add_worker_works_when_optional_fields_are_blank(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->post(route('construction.workers.store'), [
                'name' => 'Minimal Worker',
                'status' => 'active',
            ])
            ->assertRedirect(route('construction.workers.index'));

        $this->assertDatabaseHas('workers', [
            'name' => 'Minimal Worker',
            'daily_rate' => 0,
            'weekly_salary' => 0,
        ]);
    }

    public function test_add_worker_with_cleared_rate_fields_does_not_500(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->post(route('construction.workers.store'), [
                'name' => 'Empty Rates Worker',
                'daily_rate' => '',
                'weekly_salary' => '',
                'job_role' => '',
                'phone' => '',
                'nic' => '',
                'join_date' => '',
                'status' => 'active',
            ])
            ->assertRedirect(route('construction.workers.index'));

        $worker = Worker::query()->where('name', 'Empty Rates Worker')->first();
        $this->assertNotNull($worker);
        $this->assertSame(0.0, (float) $worker->daily_rate);
        $this->assertSame(0.0, (float) $worker->weekly_salary);
    }
}
