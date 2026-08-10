<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $this->route('workspace');

        return [
            'name'            => ['sometimes', 'string', 'max:255'],
            'slug'            => ['prohibited'],
            'description'     => ['sometimes', 'nullable', 'string', 'max:500'],
            'active'          => ['sometimes', 'boolean'],
            'organization_id' => ['prohibited'],
            'join_code_hash'  => ['prohibited'],
        ];
    }
}
