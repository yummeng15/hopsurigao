<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $code,
        public int $expiresMinutes = 10,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('HopSurigao password reset code')
            ->greeting('Password reset code')
            ->line('Use this 6-digit code to reset your HopSurigao password:')
            ->line('**'.$this->code.'**')
            ->line('This code expires in '.$this->expiresMinutes.' minutes.')
            ->line('If you did not request a password reset, you can ignore this email.');
    }
}
