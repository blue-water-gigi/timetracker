<?php

namespace App\Http\Requests\Organization;

use App\Enums\SubscriptionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'tin' => ['required', 'string', 'max:255'],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'subscription_status' => ['nullable', 'sometimes', 'string', Rule::enum(SubscriptionStatus::class)],
            'metadata' => ['nullable', 'json'],
        ];
    }
}
