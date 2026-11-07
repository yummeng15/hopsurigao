<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePickupLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * Pickup location name
             */
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:pickup_locations,name',
            ],

            /*
             * Address
             */
            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'latitude' => [
                'nullable',
                'required_with:longitude',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'required_with:latitude',
                'numeric',
                'between:-180,180',
            ],

            /*
             * Description
             */
            'description' => [
                'nullable',
                'string',
            ],

            /*
             * Status
             */
            'status' => [
                'required',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter a pickup location name.',

            'name.unique' => 'This pickup location already exists.',

            'address.max' => 'The address may not exceed 500 characters.',

            'status.in' => 'Please select a valid status.',
        ];
    }
}
