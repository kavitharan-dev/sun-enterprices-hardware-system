<?php

namespace App\Http\Requests\Construction;

use Illuminate\Foundation\Http\FormRequest;

class WorkerWorkDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordWork', $this->route('worker')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['project_id' => $this->input('project_id') ?: null]);
    }

    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
