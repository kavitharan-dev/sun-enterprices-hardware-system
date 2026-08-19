<?php

namespace App\Http\Requests\Cashier;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmCashierRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canConfirmTill() ?? false;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::enum(PaymentMethod::class)->except(PaymentMethod::Credit)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
