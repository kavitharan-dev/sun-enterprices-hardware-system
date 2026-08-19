<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\ProjectOwnerPaymentRequest;
use App\Models\Project;
use App\Models\ProjectOwnerPayment;
use App\Services\DailyAccountService;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;

class ProjectOwnerPaymentController extends Controller
{
    use LogsActivity;

    public function __construct(private readonly DailyAccountService $dailyAccounts) {}

    public function store(ProjectOwnerPaymentRequest $request, Project $project): RedirectResponse
    {
        return back()->with('error', 'The cashier records owner payments on Daily Accounts. This page then updates from that transaction.');
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
