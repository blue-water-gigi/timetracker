<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'sometimes', 'string', 'min:2', 'max:50', $this->nameRegexRule()],
            'last_name' => ['nullable', 'sometimes', 'string', 'min:2', 'max:50', $this->nameRegexRule()],
            'join_code' => ['required', 'string', 'max:128'],
            'workspace_id' => ['prohibited'],
            'system_role' => ['prohibited'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::defaults()],
        ];
    }

    private function nameRegexRule(): string
    {
        return 'regex:/^[\pL\s\-\']+$/u';
    }
}
