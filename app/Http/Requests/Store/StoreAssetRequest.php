<?php

namespace App\Http\Requests\Store;

use App\Enums\StoreAssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageStoreAssets() ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(StoreAssetType::class)],
            'name' => ['required', 'string', 'max:160'],
            'identifier' => ['nullable', 'string', 'max:80'],
            'vehicle_kind' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
