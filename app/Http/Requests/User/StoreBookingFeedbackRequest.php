<?php

namespace App\Http\Requests\User;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && $booking->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enjoyment' => [
                'required',
                Rule::in(['enjoyed', 'not_enjoyed']),
            ],
            'operator_service' => [
                'required',
                Rule::in(['good', 'okay', 'poor']),
            ],
            'overall_experience' => [
                'required',
                Rule::in(['great', 'okay', 'poor']),
            ],
            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
