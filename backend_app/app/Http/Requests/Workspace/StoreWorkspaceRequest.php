<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && $this->user()?->ownedOrganizations()
                ->whereKey($organization->getKey())
                ->exists();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workspaces', 'slug')->where(
                    fn($query) => $query->where('organization_id', $organization->getKey())
                ),
            ],
            'description' => ['nullable', 'string', 'max:1024'],
            'active' => ['sometimes', 'boolean'],
            'organization_id' => ['prohibited'],
        ];
    }
}
