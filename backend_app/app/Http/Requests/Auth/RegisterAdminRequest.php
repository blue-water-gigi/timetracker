<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterAdminRequest extends FormRequest
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
            'first_name' => ['nullable', 'sometimes', 'string', 'min:2', 'max:50', $this->nameRegexRule()],
            'last_name' => ['nullable', 'sometimes', 'string', 'min:2', 'max:50', $this->nameRegexRule()],
            'system_role' => ['prohibited'],
            'join_code' => ['prohibited'],
            'workspace_id' => ['prohibited'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::defaults()],
        ];
    }

    public function nameRegexRule(): string
    {
        return 'regex:/^[\pL\s\-\']+$/u';
    }
}
