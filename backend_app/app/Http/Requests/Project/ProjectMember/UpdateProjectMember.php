<?php

namespace App\Http\Requests\Project\ProjectMember;

use App\Enums\ProjectRole;
use App\Models\ProjectMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectMember extends FormRequest
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
        return [
            'project_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'project_role' => ['sometimes', 'string', Rule::enum(ProjectRole::class)],
            'approval_rank' => ['prohibited'], //depends on the role, server maps it automatically
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
