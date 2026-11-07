<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Only the Superadmin can create accounts
     * through platform user management.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->name === 'Superadmin';
    }

    /**
     * Validate the incoming request.
     *
     * This generic form currently creates
     * tourist accounts only.
     *
     * Tour Operator Admin accounts will be created
     * through the dedicated operator-management
     * workflow in the next phase.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'password' => [
                'required',
                'string',
                Password::defaults(),
                'confirmed',
            ],

            'role_id' => [
                'required',
                'integer',

                Rule::exists('roles', 'id')
                    ->where('name', 'User'),
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'role_id.exists' =>
                'Only tourist accounts can be created here. '
                . 'Tour Operator Admin accounts must be created '
                . 'through operator management.',
        ];
    }
}