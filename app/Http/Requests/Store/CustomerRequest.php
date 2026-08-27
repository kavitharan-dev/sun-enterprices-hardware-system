<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canHandleSales() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'credit_limit' => $this->input('credit_limit') ?? 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'nic' => ['nullable', 'string', 'max:30'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
