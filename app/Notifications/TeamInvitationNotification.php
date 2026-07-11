<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Organization $organization,
        public readonly Workspace $workspace,
        public readonly User $invitedBy,
        public readonly string $role,
        public readonly ?string $token = null,
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
        if (! $notifiable instanceof User) {
            throw new \InvalidArgumentException('Team invitations can only be sent to users.');
        }

        $message = (new MailMessage)
            ->subject("You're invited to {$this->organization->name} on DevPulse")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->invitedBy->name} invited you to join {$this->organization->name} on DevPulse.")
            ->line("You'll be joining the {$this->workspace->name} workspace as a {$this->role}.");

        if ($this->token) {
            $message
                ->line('Accept the invitation to create your password and start collaborating with your team.')
                ->action('Accept invitation', route('invitations.accept', [
                    'token' => $this->token,
                    'email' => $notifiable->email,
                ]))
                ->line('This invitation link expires in '.config('auth.passwords.users.expire', 60).' minutes.');
        } else {
            $message
                ->line('Your DevPulse account already exists. Sign in to access your new workspace—your password has not changed.')
                ->action('Open DevPulse', route('login'));
        }

        return $message
            ->line("If you weren't expecting this invitation, you can safely ignore this email.")
            ->salutation('The DevPulse team');
    }
}
