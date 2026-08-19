<?php

namespace App\Http\Requests\Construction;

use Illuminate\Foundation\Http\FormRequest;

class WorkerWorkDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordWork', $this->route('worker')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->input('project_id') ?: null,
            'daily_amount' => $this->input('daily_amount') ?: 0,
        ]);
    }

    public function rules(): array
    {
        return [
            // A row being updated already has its date.
            'work_date' => [$this->route('workDay') ? 'nullable' : 'required', 'date'],
            'daily_amount' => ['required', 'numeric', 'min:0'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
