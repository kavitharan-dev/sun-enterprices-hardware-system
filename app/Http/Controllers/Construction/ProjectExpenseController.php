<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\ProjectExpenseRequest;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Services\NotificationService;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;

class ProjectExpenseController extends Controller
{
    use LogsActivity;

    public function store(ProjectExpenseRequest $request, Project $project, NotificationService $notifications): RedirectResponse
    {
        $expense = $project->expenses()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $this->logActivity('created', 'ProjectExpense', "Added {$expense->category->label()} expense to {$project->project_code}", $expense);
        $notifications->maybeNotifyBudgetAlert($project->fresh());

        return back()->with('success', 'Expense recorded.');
    }

    public function destroy(Project $project, ProjectExpense $projectExpense): RedirectResponse
    {
        abort_unless($projectExpense->project_id === $project->id, 404);
        $this->authorize('manageExpenses', $project);

        if ($projectExpense->isAutomatic()) {
            return back()->with('error', 'Material expenses from issues cannot be deleted here.');
        }

        $projectExpense->delete();
        $this->logActivity('deleted', 'ProjectExpense', "Deleted expense from {$project->project_code}");

        return back()->with('success', 'Expense deleted.');
    }
}
