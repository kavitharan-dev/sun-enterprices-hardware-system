<?php

namespace Tests;

use App\Models\CashierRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function confirmPendingCashierRequests(?User $as = null): void
    {
        $confirmer = $as ?? $this->tillConfirmer();

        CashierRequest::query()->pending()->orderBy('id')->get()->each(function (CashierRequest $request) use ($confirmer) {
            $this->actingAs($confirmer)
                ->post(route('cashier.requests.confirm', $request), [
                    'payment_date' => $request->payment_date?->toDateString() ?? now()->toDateString(),
                    'method' => $request->method?->value ?? 'cash',
                    'reference' => $request->reference,
                ])
                ->assertRedirect()
                ->assertSessionHas('success');
        });
    }

    protected function tillConfirmer(): User
    {
        Role::findOrCreate('admin');

        $admin = User::query()->role('admin')->first();
        if ($admin) {
            return $admin;
        }

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
