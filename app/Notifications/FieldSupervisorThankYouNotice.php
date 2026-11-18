<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FieldSupervisorThankYouNotice extends Notification
{
    use Queueable;

    public function __construct(
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
            ->subject('Thank you for supporting a SIMS student')
            ->greeting('Thank you')
            ->line('Thank you for supervising '.$this->studentName.' during their internship at '.$this->companyName.'.')
            ->line('Your field-supervisor access for this placement has ended. You can accept another SIMS invitation if you are assigned to a future student.');
    }
}
