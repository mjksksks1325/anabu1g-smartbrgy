<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Confirms that a resident controls the email address on their portal account. Document requests stay
 * closed until the link is opened. This proves ownership of the email only; identity is established
 * earlier, when barangay staff issue the activation code after checking the resident in person.
 */
class ResidentEmailVerificationNotification extends Notification
{
    use Queueable;

    public const MINUTES_VALID = 60;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function verificationUrl(User $account): string
    {
        return URL::temporarySignedRoute('portal.verification.verify', now()->addMinutes(self::MINUTES_VALID), [
            'account' => $account->getKey(),
            'hash' => sha1(strtolower($account->email)),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new InvalidArgumentException('Resident email verification requires a user account.');
        }

        return (new MailMessage)
            ->from(config('mail.from.address'), 'Barangay Anabu I-G')
            ->subject(__('Confirm your email for the Barangay Anabu I-G Resident Portal'))
            ->view(['html' => 'mail.resident-email-verification', 'text' => 'mail.resident-email-verification-text'], [
                'verificationUrl' => self::verificationUrl($notifiable),
                'minutes' => self::MINUTES_VALID,
            ]);
    }
}
