<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An account email (password reset or Resident Portal email confirmation) was not sent because this server does not
 * deliver email (see App\MailDelivery). Sending it through the `log` mailer would only write its link to the log.
 */
class MailDeliveryUnavailableException extends RuntimeException
{
    public function __construct(string $email = 'the password reset email')
    {
        parent::__construct('Email delivery is not configured (MAIL_MAILER='.config('mail.default')."); {$email} was not sent.");
    }
}
