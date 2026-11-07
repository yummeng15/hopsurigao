<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOnsiteBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->name, ['Admin', 'Superadmin'], true);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'nationality' => ['required', 'string', 'max:100'],
            'tour_date_id' => ['required', 'integer', 'exists:tour_dates,id'],
            'pickup_schedule_id' => ['required', 'integer', 'exists:pickup_schedules,id'],
            'number_of_guests' => ['required', 'integer', 'min:1'],
            'special_request' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
