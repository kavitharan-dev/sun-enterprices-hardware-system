<?php

namespace App\Http\Requests\Construction;

use Illuminate\Foundation\Http\FormRequest;

class SettleWorkerWeekRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePayroll', $this->route('worker')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'debt_deducted' => $this->input('debt_deducted') ?: 0,
            'project_id' => $this->input('project_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'debt_deducted' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
