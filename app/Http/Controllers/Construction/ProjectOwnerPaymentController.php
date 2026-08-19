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
        $payment = $project->ownerPayments()->create([
            ...$request->validated(),
            'received_by' => $request->user()->id,
        ]);

        $this->logActivity(
            'received',
            'ProjectOwnerPayment',
            'Recorded owner payment of Rs. '.number_format((float) $payment->amount, 2)." for {$project->project_code}",
            $payment,
        );

        $this->dailyAccounts->postOwnerPayment($payment->setRelation('project', $project));

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
