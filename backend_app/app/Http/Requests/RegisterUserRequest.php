<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SystemRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
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
            'workspace_id' => ['required', Rule::exists('workspaces', 'id')],
            'nickname' => ['required', 'string', 'min:1', 'max:30', 'regex:/^[\pL\pN_-]+$/u', Rule::unique('users', 'nickname')],
            'first_name' => ['nullable', 'string', 'min:2', 'max:50', $this->nameRegexRules('first_name')],
            'last_name' => ['nullable', 'string', 'min:2', 'max:50', $this->nameRegexRules('last_name')],
            'system_role' => ['nullable', 'string', Rule::enum(SystemRole::class)->only(SystemRole::USER)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::defaults()],
        ];
    }

    protected function nameRegexRules(?string $key = null): string|array
    {
        $rules = [
            'first_name' => 'regex:/^[\pL\s\-\']+$/u',
            'last_name' => 'regex:/^[\pL\s\-\']+$/u',
        ];

        return isset($key)
            ? $rules[$key]
            : $rules;
    }
}
