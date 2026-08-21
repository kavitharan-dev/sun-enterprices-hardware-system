<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;

class CloseDailyAccountDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canConfirmTill() ?? false;
    }

    public function rules(): array
    {
        return [
            'business_date' => ['required', 'date'],
            'counted_cash' => ['nullable', 'numeric'],
            'close_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
