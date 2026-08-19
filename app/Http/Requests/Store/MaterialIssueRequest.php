<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class MaterialIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canIssueMaterials() ?? false;
    }

    public function rules(): array
    {
        return [
            'issue_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:material_request_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }
}
