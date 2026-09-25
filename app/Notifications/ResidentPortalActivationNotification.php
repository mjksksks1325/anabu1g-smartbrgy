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
            ->subject('Barangay Anabu I-G Resident Portal activation')
            ->greeting('Continue your Resident Portal registration')
            ->line('Resident Number: '.$this->residentNumber)
            ->line('Activation Code: '.$this->activationCode)
            ->line('Enter both on the Resident Portal registration page. This code expires in 24 hours and is replaced if another code is issued.')
            ->line('If you did not request this, contact Barangay Anabu I-G. Do not share this code.');
    }
}
