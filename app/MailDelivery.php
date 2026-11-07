<?php

namespace App;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Whether this server actually sends email, and the record kept when a password reset email could not be sent.
 * The `log` mailer only writes messages, with their links, to the application log: a reset email sent that way
 * never reaches the owner and leaves a working reset token in a log file.
 */
class MailDelivery
{
    /**
     * Whether the configured mailer delivers messages. `array` keeps them in memory and is accepted only while
     * the test suite runs, where it stands in for a real mailer.
     */
    public static function configured(): bool
    {
        $mailer = (string) config('mail.default');
        $undelivered = app()->runningUnitTests() ? ['log'] : ['log', 'array'];

        return $mailer !== '' && ! in_array($mailer, $undelivered, true) && is_array(config("mail.mailers.{$mailer}"));
    }

    /**
     * What an administrator must fix before account emails (password reset, activation, email confirmation)
     * can reach anyone. Configuration names only; never credentials.
     *
     * @return list<string>
     */
    public static function problems(): array
    {
        $problems = [];
        if (! self::configured()) {
            $problems[] = 'Email delivery is off (MAIL_MAILER='.config('mail.default').'): password reset links are not sent, and activation and email confirmation messages are only written to the application log. Configure an SMTP provider.';
        }
        if (Str::endsWith(Str::lower((string) config('mail.from.address')), ['@example.com', '@example.org', '@example.net'])) {
            $problems[] = 'The sender address is still the placeholder '.config('mail.from.address').'. Mail providers reject or flag it: set MAIL_FROM_ADDRESS to an address verified with the provider.';
        }

        return $problems;
    }

    /**
     * Record in the audit log that a reset email for an existing account was not sent, so administrators can see
     * failed deliveries without reading log files. Holds the account id only: no email address, link or token.
     */
    public static function recordResetEmailFailure(Request $request, Throwable $exception, ?User $actor = null): void
    {
        $account = User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim((string) $request->input('email')))])->first();
        if ($account === null) {
            return;
        }

        DB::table('administrative_audits')->insert([
            'user_id' => $actor?->id,
            'actor' => $actor?->name ?? 'Password reset request',
            'action' => $exception instanceof Exceptions\MailDeliveryUnavailableException ? 'auth.password-reset-mail-unavailable' : 'auth.password-reset-mail-failed',
            'type' => 'security',
            'record' => $account->isResidentAccount() ? 'resident_portal:'.$account->id : (string) $account->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
