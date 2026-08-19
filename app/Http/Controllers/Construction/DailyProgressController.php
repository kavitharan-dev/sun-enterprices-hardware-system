<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Controller;
use App\Http\Requests\Construction\DailyProgressRequest;
use App\Models\DailyProgress;
use App\Models\Project;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;

class DailyProgressController extends Controller
{
    use LogsActivity;

    public function store(DailyProgressRequest $request, Project $project): RedirectResponse
    {
        $progress = $project->dailyProgress()->create([
            ...$request->validated(),
            'recorded_by' => $request->user()->id,
        ]);

        $this->syncProjectPercentage($project, $progress);
        $this->logActivity('created', 'DailyProgress', "Logged progress for {$project->project_code} on {$progress->progress_date->toDateString()}", $progress);

        return back()->with('success', 'Daily progress saved.');
    }

    public function update(DailyProgressRequest $request, Project $project, DailyProgress $dailyProgress): RedirectResponse
    {
        abort_unless($dailyProgress->project_id === $project->id, 404);
        $this->authorize('recordProgress', $project);

        $dailyProgress->update($request->validated());
        $this->syncProjectPercentage($project, $dailyProgress->fresh());
        $this->logActivity('updated', 'DailyProgress', "Updated progress for {$project->project_code}", $dailyProgress);

        return back()->with('success', 'Daily progress updated.');
    }

    public function destroy(Project $project, DailyProgress $dailyProgress): RedirectResponse
    {
        abort_unless($dailyProgress->project_id === $project->id, 404);
        $this->authorize('recordProgress', $project);

        $dailyProgress->delete();

        $latest = $project->dailyProgress()->latest('progress_date')->first();
        $project->update([
            'progress_percentage' => $latest?->progress_percentage ?? 0,
        ]);

        return back()->with('success', 'Progress entry deleted.');
    }

    private function syncProjectPercentage(Project $project, DailyProgress $progress): void
    {
        if ($progress->progress_percentage === null) {
            return;
        }

        $project->update(['progress_percentage' => $progress->progress_percentage]);
    }
}
