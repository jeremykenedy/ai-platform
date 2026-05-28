<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $inviteToken,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = config('app.frontend_url', config('app.url')).'/set-password/'.$this->inviteToken;

        return (new MailMessage())
            ->subject('Welcome to '.config('app.name'))
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('An account has been created for you on '.config('app.name').'.')
            ->line('Please click the button below to set your password and get started.')
            ->action('Set Your Password', $url)
            ->line('This link will expire once you set your password.')
            ->line('If you did not expect this invitation, no action is required.');
    }
}
