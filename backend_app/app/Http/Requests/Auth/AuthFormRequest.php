<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\EmailNormalizer;
use Illuminate\Foundation\Http\FormRequest;

abstract class AuthFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (! is_string($email)) {
            return;
        }

        $this->merge([
            'email' => EmailNormalizer::normalize($email),
        ]);
    }
}
