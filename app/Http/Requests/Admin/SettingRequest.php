<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:180'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'currency' => ['required', 'string', 'max:10'],
            'currency_code' => ['required', 'string', 'max:10'],
            'invoice_prefix' => ['required', 'string', 'max:12'],
            'purchase_prefix' => ['required', 'string', 'max:12'],
            'material_request_prefix' => ['required', 'string', 'max:12'],
            'material_issue_prefix' => ['required', 'string', 'max:12'],
            'project_prefix' => ['required', 'string', 'max:12'],
            'worker_prefix' => ['required', 'string', 'max:12'],
            'timezone' => ['required', 'timezone'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }
}
