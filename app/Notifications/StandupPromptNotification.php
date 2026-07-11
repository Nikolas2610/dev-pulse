<?php

namespace App\Notifications;

use App\Models\StandupRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StandupPromptNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly StandupRun $run,
        public readonly bool $reminder = false,
        public readonly ?string $message = null,
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
        $this->run->loadMissing('workspace');
        $name = $notifiable instanceof User ? $notifiable->name : '';

        return (new MailMessage)
            ->subject(($this->reminder ? 'Reminder: ' : '').'Submit your standup')
            ->greeting('Hi '.$name.',')
            ->line($this->message ?? (($this->reminder ? 'This is a reminder to submit' : 'Please submit').' your standup for '.$this->run->workspace->name.'.'))
            ->action('Submit standup', route('standup-runs.submit', $this->run));
    }
}
