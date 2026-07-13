<?php

namespace App\Http\Requests\Organization;

use App\Enums\SubscriptionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'tin' => ['sometimes', 'string', 'max:255'],
            'plan_id' => ['sometimes', 'integer', Rule::exists('plans', 'id')],
            'subscription_status' => ['sometimes', 'string', Rule::enum(SubscriptionStatus::class)],
            'metadata' => ['sometimes', 'json'],
        ];
    }
}
