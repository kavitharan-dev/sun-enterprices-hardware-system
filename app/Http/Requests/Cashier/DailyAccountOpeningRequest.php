<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;

class DailyAccountOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDailyAccounts() ?? false;
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
