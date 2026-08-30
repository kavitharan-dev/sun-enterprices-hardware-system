<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageStoreAssets() ?? false;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'exists:workers,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'issued_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
