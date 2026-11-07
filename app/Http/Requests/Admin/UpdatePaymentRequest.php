<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->name === 'Superadmin';
    }

    public function rules(): array
    {
        return [
            'booking_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:bookings,id',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],
            'payment_method' => [
                'required',
                'string',
                'max:100',
            ],
            'payment_status' => [
                'required',
                'string',
                Rule::in(['pending', 'paid', 'failed', 'refunded', 'cancelled']),
            ],
            'transaction_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'paid_at' => [
                'nullable',
                'date',
            ],
            'proof_of_payment' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'booking_id.exists' => 'The selected booking was not found.',
            'amount.required' => 'Please enter the payment amount.',
            'amount.numeric' => 'The payment amount must be a valid number.',
            'amount.min' => 'The payment amount must be at least 0.01.',
            'payment_method.required' => 'Please select or enter a payment method.',
            'payment_status.required' => 'Please select a payment status.',
            'payment_status.in' => 'Please select a valid payment status.',
            'proof_of_payment.mimes' => 'Proof of payment must be a JPG, PNG, WEBP image or PDF document.',
            'proof_of_payment.max' => 'Proof of payment file size must not exceed 5MB.',
        ];
    }
}
