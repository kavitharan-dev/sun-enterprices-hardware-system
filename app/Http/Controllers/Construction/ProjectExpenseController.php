<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\ProjectExpenseRequest;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Services\DailyAccountService;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;

class ProjectExpenseController extends Controller
{
    use LogsActivity;

    public function __construct(private readonly DailyAccountService $dailyAccounts) {}

    public function store(ProjectExpenseRequest $request, Project $project): RedirectResponse
    {
        return back()->with('error', 'The cashier records cash site expenses on Daily Accounts. This page then updates from that transaction.');
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
