<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $this->route('organization');

        return [
            'name'            => ['required', 'string', 'max:255'],
            'slug'            => ['prohibited'],
            'description'     => ['nullable', 'string', 'max:1024'],
            'active'          => ['sometimes', 'boolean'],
            'organization_id' => ['prohibited'],
        ];
    }
}
