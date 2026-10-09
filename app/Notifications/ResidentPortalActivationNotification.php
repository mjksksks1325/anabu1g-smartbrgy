<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResidentPortalActivationNotification extends Notification
{
    use Queueable;

    public function __construct(public string $residentNumber, public string $activationCode) {}

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
            ->from(config('mail.from.address'), 'Barangay Anabu I-G')
            ->subject(__('Barangay Anabu I-G Resident Portal Activation'))
            ->view([
                'html' => 'mail.resident-activation',
                'text' => 'mail.resident-activation-text',
            ], [
                'residentNumber' => $this->residentNumber,
                'activationCode' => $this->activationCode,
                'registrationUrl' => route('portal.register'),
            ]);
    }
}
