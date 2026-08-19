<?php

namespace App\Http\Requests\Construction;

use Illuminate\Foundation\Http\FormRequest;

class WorkerAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePayroll', $this->route('worker')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'deduct_from_week' => $this->boolean('deduct_from_week'),
            'project_id' => $this->input('project_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'deduct_from_week' => ['required', 'boolean'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Enter the amount given to the worker.',
        ];
    }
}
