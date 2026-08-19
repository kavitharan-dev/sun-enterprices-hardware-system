<?php

namespace App\Http\Requests\Cashier;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyAccountEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDailyAccounts() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->input('project_id') ?: null,
            'worker_id' => $this->input('worker_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'occurred_on' => ['required', 'date'],
            'type' => ['required', Rule::in([DailyAccountType::OtherIncome->value, DailyAccountType::OtherExpense->value])],
            'category' => ['required', Rule::enum(DailyAccountCategory::class)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'worker_id' => ['nullable', 'exists:workers,id'],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)->except(PaymentMethod::Credit)],
        ];
    }
}
