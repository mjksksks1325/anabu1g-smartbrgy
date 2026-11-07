<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A password reset email was not sent because this server does not deliver email (see App\MailDelivery).
 * Sending it through the `log` mailer would only write the reset link, with its token, to the application log.
 */
class MailDeliveryUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email delivery is not configured (MAIL_MAILER='.config('mail.default').'); the password reset email was not sent.');
    }
}
