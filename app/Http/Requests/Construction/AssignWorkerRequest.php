<?php

namespace App\Http\Requests\Construction;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canViewConstruction() ?? false;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'exists:workers,id'],
            'role_on_site' => ['nullable', 'string', 'max:80'],
            'assigned_from' => ['required', 'date'],
            'assigned_to' => ['nullable', 'date', 'after_or_equal:assigned_from'],
        ];
    }
}
