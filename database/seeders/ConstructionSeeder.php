<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\WorkerStatus;
use App\Models\Customer;
use App\Models\MaterialRequest;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Services\MaterialRequestService;
use Illuminate\Database\Seeder;

class ConstructionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@hardware.local')->first();
        $siteManager = User::query()->where('email', 'site@hardware.local')->first();
        $customer = Customer::query()->where('name', 'Kumar')->first();

        if (! $admin || ! $siteManager || ! $customer) {
            return;
        }

        $project = Project::query()->updateOrCreate(
            ['project_code' => 'PRJ-DEMO-0001'],
            [
                'name' => 'Kumar Residence',
                'customer_id' => $customer->id,
                'location' => 'Nugegoda',
                'description' => 'Two-storey house construction.',
                'budget' => 4500000,
                'start_date' => now()->toDateString(),
                'expected_end_date' => now()->addMonths(8)->toDateString(),
                'status' => ProjectStatus::Active,
                'progress_percentage' => 0,
                'site_manager_id' => $siteManager->id,
                'created_by' => $admin->id,
            ],
        );

        $sunil = Worker::query()->updateOrCreate(
            ['worker_code' => 'WRK-DEMO-0001'],
            [
                'name' => 'Sunil Perera',
                'nic' => '198012345678',
                'phone' => '0775550001',
                'job_role' => 'Mason',
                'daily_rate' => 4500,
                'join_date' => now()->subYear()->toDateString(),
                'status' => WorkerStatus::Active,
            ],
        );

        $ravi = Worker::query()->updateOrCreate(
            ['worker_code' => 'WRK-DEMO-0002'],
            [
                'name' => 'Ravi Fernando',
                'nic' => '199012345678',
                'phone' => '0775550002',
                'job_role' => 'Labourer',
                'daily_rate' => 2800,
                'join_date' => now()->subMonths(6)->toDateString(),
                'status' => WorkerStatus::Active,
            ],
        );

        if (! $project->workers()->where('worker_id', $sunil->id)->exists()) {
            $project->workers()->attach($sunil->id, [
                'role_on_site' => 'Lead mason',
                'assigned_from' => now()->toDateString(),
            ]);
        }

        if (! $project->workers()->where('worker_id', $ravi->id)->exists()) {
            $project->workers()->attach($ravi->id, [
                'role_on_site' => 'General labour',
                'assigned_from' => now()->toDateString(),
            ]);
        }

        if (MaterialRequest::query()->exists()) {
            return;
        }

        $cement = Product::query()->where('sku', 'CEM-001')->first();
        $bricks = Product::query()->where('sku', 'BRK-001')->first();

        if (! $cement || ! $bricks) {
            return;
        }

        $service = app(MaterialRequestService::class);

        $request = $service->create([
            'project_id' => $project->id,
            'request_date' => now()->toDateString(),
            'required_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Foundation pour this week',
        ], [
            ['product_id' => $cement->id, 'quantity' => 40, 'notes' => '50kg bags'],
            ['product_id' => $bricks->id, 'quantity' => 500],
        ], $siteManager->id);

        $service->submit($request);
    }
}
