<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $password
    )
    {
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $loginUrl = rtrim(config('app.frontend_url'), '/') . '/login';

        return (new MailMessage)
            ->subject('Your ' . config('app.name') . ' Account Credentials')
            ->greeting('Hello, ' . $notifiable->name . '!')
            ->line('An account has been created for you. Use the credentials below to log in.')
            ->line('**Email:** ' . $notifiable->email)
            ->line('**Password:** ' . $this->password)
            ->action('Login Now', $loginUrl)
            ->line('Please change your password after logging in.')
            ->line('If you did not expect this email, please contact support.');
    }
}
