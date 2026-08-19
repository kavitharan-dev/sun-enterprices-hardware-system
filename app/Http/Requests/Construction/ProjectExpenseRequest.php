<?php

namespace App\Http\Requests\Construction;

use App\Enums\ExpenseCategory;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && $this->user()?->can('manageExpenses', $project);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(ExpenseCategory::class)->except(ExpenseCategory::Material)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
        ];
    }
}
