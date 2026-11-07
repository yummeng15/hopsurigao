<?php

namespace App\Notifications;

use App\Models\TourOperator;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperatorBusinessDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TourOperator $operator,
        public string $decision
    ) {}

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
        $approved = $this->decision === 'approved';

        return [
            'type' => $approved ? 'operator_business_approved' : 'operator_business_rejected',
            'title' => $approved ? 'Business approved' : 'Business registration rejected',
            'message' => $approved
                ? $this->operator->name.' is now active.'
                : $this->operator->name.' was rejected. '.($this->operator->rejection_reason ?? ''),
            'action_url' => '/operator/business',
            'action_text' => 'View My Business',
            'related_entity' => 'tour_operator',
            'related_id' => $this->operator->id,
            'priority' => 'normal',
            'icon' => 'building',
        ];
    }
}
