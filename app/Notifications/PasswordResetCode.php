<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCode extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $code,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your password reset code'))
            ->markdown('mail.password-reset-code', [
                'code' => $this->code,
                'expiresInMinutes' => config('auth.passwords.users.expire'),
            ]);
    }
}
