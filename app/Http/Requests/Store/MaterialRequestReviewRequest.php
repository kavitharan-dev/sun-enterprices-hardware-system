<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class MaterialRequestReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReviewMaterialRequests() ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:material_request_items,id'],
            'items.*.quantity_approved' => ['required', 'numeric', 'min:0'],
        ];
    }
}
