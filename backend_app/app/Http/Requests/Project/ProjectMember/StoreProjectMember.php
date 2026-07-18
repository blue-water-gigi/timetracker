<?php

namespace App\Http\Requests\Project\ProjectMember;

use App\Enums\ProjectRole;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
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
        return [
            'project_id' => ['prohibited'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'project_role' => ['required', 'string', Rule::enum(ProjectRole::class)],
            'approval_rank' => ['prohibited'], //depends on the role, server maps it automatically
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
