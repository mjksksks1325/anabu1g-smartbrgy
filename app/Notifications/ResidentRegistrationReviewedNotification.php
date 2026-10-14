<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class ResidentRegistrationReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $reference,
        public string $decision,
        public ?string $remarks,
        public string $reviewedAt,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$statusLabel, $instructions, $actionLabel, $actionUrl] = match ($this->decision) {
            'approved' => ['Approved', 'Your resident registration is approved. Complete your portal account registration to receive an activation code.', 'Continue Registration', route('portal.register', ['step' => 'activation'])],
            'declined' => ['Declined', 'Your registration application was declined. Read the reason below. Contact barangay staff if you need clarification.', 'Get help', route('portal.information').'#help'],
            'needs_correction' => ['Needs correction', 'Please correct your application using the remarks below, then submit it again for review. Open it in the browser where you originally submitted it.', 'Open my application', route('portal.new-resident.status')],
            default => throw new InvalidArgumentException('Unsupported registration review decision.'),
        };

        return (new MailMessage)
            ->from(config('mail.from.address'), 'Barangay Anabu I-G')
            ->subject(__('Resident registration update').': '.__($statusLabel))
            ->view(['html' => 'mail.resident-registration-reviewed', 'text' => 'mail.resident-registration-reviewed-text'], [
                'reference' => $this->reference, 'statusLabel' => $statusLabel,
                'remarks' => $this->remarks, 'reviewedAt' => $this->reviewedAt,
                'instructions' => $instructions, 'actionLabel' => $actionLabel, 'actionUrl' => $actionUrl,
            ]);
    }
}
