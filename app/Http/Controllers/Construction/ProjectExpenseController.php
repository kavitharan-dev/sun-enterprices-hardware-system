<?php

namespace App\Http\Controllers\Construction;

use App\Enums\CashierRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\ProjectExpenseRequest;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Services\CashierRequestService;
use App\Services\DailyAccountService;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;

class ProjectExpenseController extends Controller
{
    use LogsActivity;

    public function __construct(
        private readonly DailyAccountService $dailyAccounts,
        private readonly CashierRequestService $cashier,
    ) {}

    public function store(ProjectExpenseRequest $request, Project $project): RedirectResponse
    {
        try {
            $queued = $this->cashier->submit(
                CashierRequestType::ProjectExpense,
                [
                    'amount' => $request->input('amount'),
                    'category' => $request->input('category'),
                    'expense_date' => $request->input('expense_date'),
                    'expense_description' => $request->input('description'),
                    'project_id' => $project->id,
                    'payment_date' => $request->input('expense_date'),
                    'description' => $project->name.': '.$request->input('description'),
                ],
                $request->user(),
            );
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        if ($queued->isPending()) {
            return back()->with('success', 'Sent to the cashier. Project expenses update when the cashier pays.');
        }

        return back()->with('success', 'Expense recorded.');
    }

    public function destroy(Project $project, ProjectExpense $projectExpense): RedirectResponse
    {
        abort_unless($projectExpense->project_id === $project->id, 404);
        $this->authorize('manageExpenses', $project);

        if ($projectExpense->isAutomatic()) {
            return back()->with('error', 'Material expenses from issues cannot be deleted here.');
        }

        $this->dailyAccounts->removeFor($projectExpense);
        $projectExpense->delete();
        $this->logActivity('deleted', 'ProjectExpense', "Deleted expense from {$project->project_code}");

        return back()->with('success', 'Expense deleted.');
    }
}
