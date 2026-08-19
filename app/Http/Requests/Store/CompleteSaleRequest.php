<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompleteSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canHandleSales() ?? false;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'in:cash,card,bank_transfer,credit'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->requirePaidAmountUnlessCredit($validator);
        });
    }

    public function requirePaidAmountUnlessCredit(Validator $validator): void
    {
        $method = (string) $this->input('payment_method', 'cash');

        if ($method === 'credit') {
            return;
        }

        if ((float) $this->input('payment_amount', 0) <= 0) {
            $validator->errors()->add(
                'payment_amount',
                'Enter the amount the customer paid. Cash, card, and bank transfer cannot print a bill without payment. Use Credit if they will pay later.',
            );
        }
    }
}
