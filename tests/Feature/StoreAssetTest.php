<?php

namespace Tests\Feature;

use App\Enums\StoreAssetType;
use App\Enums\WorkerStatus;
use App\Models\StoreAsset;
use App\Models\StoreAssetAssignment;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_manager_can_register_issue_and_return_asset(): void
    {
        [$manager, $worker] = $this->seedStoreManager();

        $this->actingAs($manager)
            ->post(route('store.assets.store'), [
                'type' => StoreAssetType::Vehicle->value,
                'name' => 'Site Tractor',
                'identifier' => 'TR-45',
                'vehicle_kind' => 'Tractor',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $asset = StoreAsset::query()->first();
        $this->assertNotNull($asset);
        $this->assertSame('Site Tractor', $asset->name);

        $this->actingAs($manager)
            ->post(route('store.assets.issue', $asset), [
                'worker_id' => $worker->id,
                'issued_at' => now()->format('Y-m-d\TH:i'),
                'purpose' => 'Transport sand',
            ])
            ->assertRedirect();

        $assignment = StoreAssetAssignment::query()->first();
        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->isOpen());

        $this->actingAs($manager)
            ->get(route('store.assets.index'))
            ->assertOk()
            ->assertSee('Site Tractor')
            ->assertSee($worker->name);

        $this->actingAs($manager)
            ->post(route('store.asset-assignments.return', $assignment))
            ->assertRedirect();

        $this->assertFalse($assignment->fresh()->isOpen());
    }

    public function test_cashier_can_view_assets_but_not_register(): void
    {
        Role::findOrCreate('cashier');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($cashier)
            ->get(route('store.assets.index'))
            ->assertOk();

        $this->actingAs($cashier)
            ->get(route('store.assets.create'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Worker}
     */
    private function seedStoreManager(): array
    {
        Role::findOrCreate('store_manager');

        $manager = User::factory()->create();
        $manager->assignRole('store_manager');

        $worker = Worker::query()->create([
            'worker_code' => 'W-001',
            'name' => 'Sunil',
            'phone' => '0772222222',
            'daily_rate' => 2500,
            'weekly_salary' => 0,
            'join_date' => now()->toDateString(),
            'status' => WorkerStatus::Active,
        ]);

        return [$manager, $worker];
    }
}
