<?php

namespace App\Http\Controllers\Construction;

use App\Enums\CashierRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\ProjectOwnerPaymentRequest;
use App\Models\Project;
use App\Models\ProjectOwnerPayment;
use App\Services\CashierRequestService;
use App\Services\DailyAccountService;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ProjectOwnerPaymentController extends Controller
{
    use LogsActivity;

    public function __construct(
        private readonly DailyAccountService $dailyAccounts,
        private readonly CashierRequestService $cashier,
    ) {}

    public function store(ProjectOwnerPaymentRequest $request, Project $project): RedirectResponse
    {
        try {
            $queued = $this->cashier->submit(
                CashierRequestType::OwnerPayment,
                [
                    ...$request->validated(),
                    'project_id' => $project->id,
                    'description' => 'Site owner payment for '.$project->name
                        .' — Rs. '.number_format((float) $request->input('amount'), 2),
                ],
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        if ($queued->isPending()) {
            return back()->with('success', 'Sent to the cashier. Project received totals update when the cashier confirms the money.');
        }

        return back()->with('success', 'Site owner payment recorded. Budget remaining and cash balance have been updated.');
    }

    public function destroy(Project $project, ProjectOwnerPayment $ownerPayment): RedirectResponse
    {
        abort_unless($ownerPayment->project_id === $project->id, 404);
        $this->authorize('recordOwnerPayments', $project);

        $amount = (float) $ownerPayment->amount;
        $this->dailyAccounts->removeFor($ownerPayment);
        $ownerPayment->delete();

        $this->logActivity(
            'deleted',
            'ProjectOwnerPayment',
            'Deleted owner payment of Rs. '.number_format($amount, 2)." from {$project->project_code}",
        );

        return back()->with('success', 'Owner payment removed. Budget remaining and cash balance have been updated.');
    }
}
