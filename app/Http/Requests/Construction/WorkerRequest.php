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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'nic' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_role' => ['nullable', 'string', 'max:80'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'join_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(WorkerStatus::class)],
        ];
    }
}
