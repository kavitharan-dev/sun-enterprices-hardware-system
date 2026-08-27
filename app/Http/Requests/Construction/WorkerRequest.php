<?php

namespace App\Http\Requests\Construction;

use App\Enums\WorkerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageWorkers() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Empty number inputs become null via ConvertEmptyStringsToNull;
        // workers.daily_rate / weekly_salary are NOT NULL, so coerce before insert.
        $this->merge([
            'daily_rate' => $this->input('daily_rate') ?? 0,
            'weekly_salary' => $this->input('weekly_salary') ?? 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'nic' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_role' => ['nullable', 'string', 'max:80'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'weekly_salary' => ['required', 'numeric', 'min:0'],
            'join_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(WorkerStatus::class)],
        ];
    }
}
