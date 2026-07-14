<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->ownedOrganizations()
            ->whereKey($this->integer('organization_id'))
            ->exists() ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workspaces', 'slug')->where(
                    fn ($query) => $query->where('organization_id', $this->integer('organization_id'))
                ),
            ],
            'description' => ['nullable', 'string', 'max:1024'],
            'active' => ['sometimes', 'boolean'],
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
        ];
    }
}
