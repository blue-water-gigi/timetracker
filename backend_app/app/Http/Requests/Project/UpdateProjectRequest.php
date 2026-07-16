<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && $workspace->projects()
                ->where('workspace_id', $workspace->id)
                ->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workspace_id' => ['sometimes', 'integer', Rule::exists('workspaces', 'workspace_id')],
            'created_by_user_id' => ['prohibited'],
            'updated_by_user_id' => ['prohibited'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:500'],
            'slug' => ['sometimes', 'string', Rule::unique('projects', 'slug')],
            'active' => ['sometimes', 'boolean'],
            'period_start' => ['nullable', 'sometimes', 'date'],
            'period_end' => ['nullable', 'sometimes', 'date'],
        ];
    }
}
