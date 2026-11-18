<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FieldSupervisorInvitationNotice extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $acceptUrl,
        private readonly string $studentName,
        private readonly string $companyName,
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
            ->subject('SIMS field supervisor invitation')
            ->greeting('Hello')
            ->line($this->studentName.' has listed you as the field supervisor for '.$this->companyName.'.')
            ->line('Accept the invitation to activate or connect your SIMS account and review the student’s internship activities.')
            ->action('Accept invitation', $this->acceptUrl)
            ->line('This invitation expires in seven days.');
    }
}
