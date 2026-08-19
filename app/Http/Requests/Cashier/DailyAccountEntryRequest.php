<?php

namespace App\Http\Requests\Cashier;

use App\Enums\DailyAccountCategory;
use App\Enums\DailyAccountType;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyAccountEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canConfirmTill() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->input('project_id') ?: null,
            'worker_id' => $this->input('worker_id') ?: null,
            'sale_id' => $this->input('sale_id') ?: null,
            'purchase_id' => $this->input('purchase_id') ?: null,
            'deduct_from_week' => $this->boolean('deduct_from_week'),
            'debt_deducted' => $this->input('debt_deducted') ?: 0,
        ]);
    }

    public function rules(): array
    {
        $type = (string) $this->input('type');

        return [
            'occurred_on' => ['required', 'date'],
            'type' => ['required', Rule::enum(DailyAccountType::class)],
            'amount' => [
                Rule::requiredIf(! in_array($type, [DailyAccountType::Purchase->value], true)),
                'nullable',
                'numeric',
                'min:0',
            ],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)->except(PaymentMethod::Credit)],
            'reference_no' => ['nullable', 'string', 'max:80'],
            'description' => [
                Rule::requiredIf(in_array($type, [
                    DailyAccountType::OtherIncome->value,
                    DailyAccountType::OtherExpense->value,
                    DailyAccountType::ProjectExpense->value,
                ], true)),
                'nullable',
                'string',
                'max:255',
            ],
            'sale_id' => [
                Rule::requiredIf($type === DailyAccountType::Sale->value),
                'nullable',
                'exists:sales,id',
            ],
            'purchase_id' => [
                Rule::requiredIf($type === DailyAccountType::Purchase->value),
                'nullable',
                'exists:purchases,id',
            ],
            'project_id' => [
                Rule::requiredIf(in_array($type, [
                    DailyAccountType::OwnerPayment->value,
                    DailyAccountType::ProjectExpense->value,
                ], true)),
                'nullable',
                'exists:projects,id',
            ],
            'worker_id' => [
                Rule::requiredIf(in_array($type, [
                    DailyAccountType::WorkerAdvance->value,
                    DailyAccountType::WorkerSettlement->value,
                ], true)),
                'nullable',
                'exists:workers,id',
            ],
            'expense_category' => [
                Rule::requiredIf($type === DailyAccountType::ProjectExpense->value),
                'nullable',
                Rule::enum(ExpenseCategory::class)->except(ExpenseCategory::Material),
            ],
            'category' => [
                Rule::requiredIf(in_array($type, [
                    DailyAccountType::OtherIncome->value,
                    DailyAccountType::OtherExpense->value,
                ], true)),
                'nullable',
                Rule::enum(DailyAccountCategory::class),
            ],
            'deduct_from_week' => ['sometimes', 'boolean'],
            'debt_deducted' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = (string) $this->input('type');
            $amount = (float) $this->input('amount', 0);

            if ($type === DailyAccountType::Purchase->value) {
                return;
            }

            if ($type === DailyAccountType::WorkerSettlement->value) {
                return;
            }

            if ($amount <= 0) {
                $validator->errors()->add('amount', 'Enter the amount the cashier received or paid.');
            }
        });
    }
}
