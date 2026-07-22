<?php

declare(strict_types=1);

namespace App\Http\Requests\Project\ProjectMember;

use App\Enums\ProjectRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMember extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $ownerId = $project->workspace->organization->owner_id;

        return [
            'project_id' => ['prohibited'],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where(function (Builder $query) use ($project, $ownerId) {
                        $query->where('workspace_id', $project->workspace_id)
                            ->orWhere('id', $ownerId);
                    }),
                Rule::unique('project_members', 'user_id')
                    ->where('project_id', $project->getKey()),
            ],
            'project_role' => ['required', 'string', Rule::enum(ProjectRole::class)],
            'approval_rank' => ['prohibited'], // depends on the role, server maps it automatically
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
