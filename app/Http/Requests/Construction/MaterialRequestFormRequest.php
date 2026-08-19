<?php

namespace App\Http\Requests\Construction;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class MaterialRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canCreateMaterialRequests() ?? false;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $projectId = $this->input('project_id');

            if (! $user || ! $projectId || $user->hasRole('admin')) {
                return;
            }

            $project = Project::query()->find($projectId);

            if (! $project || ! $project->isAssignedTo($user)) {
                $validator->errors()->add('project_id', 'You can only request materials for projects assigned to you.');
            }
        });
    }
}
