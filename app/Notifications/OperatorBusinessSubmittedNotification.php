<?php

namespace App\Notifications;

use App\Models\TourOperator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperatorBusinessSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public TourOperator $operator) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'operator_business_submitted',
            'title' => 'Tour operator registration submitted',
            'message' => $this->operator->name.' is waiting for approval.',
            'action_url' => '/superadmin/operators',
            'action_text' => 'Review businesses',
            'related_entity' => 'tour_operator',
            'related_id' => $this->operator->id,
            'priority' => 'normal',
            'icon' => 'building',
        ];
    }
}
