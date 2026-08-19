<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canHandleSales() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('customer_id') === '') {
            $this->merge(['customer_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'walk_in_name' => ['nullable', 'string', 'max:180', 'required_without:customer_id'],
            'sale_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,card,bank_transfer,credit'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('complete') || ! $this->user()?->canConfirmTill()) {
                return;
            }

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
        });
    }
}
