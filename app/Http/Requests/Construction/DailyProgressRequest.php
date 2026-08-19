<?php

namespace App\Http\Requests\Construction;

use App\Models\DailyProgress;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DailyProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('recordProgress', $project);
    }

    public function rules(): array
    {
        return [
            'progress_date' => ['required', 'date'],
            'work_completed' => ['required', 'string'],
            'workers_present' => ['required', 'integer', 'min:0'],
            'progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $project = $this->route('project');
            $progress = $this->route('dailyProgress');

            if (! $project instanceof Project || ! $this->filled('progress_date')) {
                return;
            }

            $exists = DailyProgress::query()
                ->where('project_id', $project->id)
                ->whereDate('progress_date', $this->date('progress_date')->toDateString())
                ->when(
                    $progress instanceof DailyProgress,
                    fn ($query) => $query->where('id', '!=', $progress->id),
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add('progress_date', 'Progress is already logged for this date.');
            }
        });
    }

}
