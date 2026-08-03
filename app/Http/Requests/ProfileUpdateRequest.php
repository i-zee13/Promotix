<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'timezone' => ['nullable', 'timezone:all'],
            'reporting_timezone' => ['nullable', 'string', Rule::in(\App\Support\UserTimezone::REPORTING_MODES)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'support_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
