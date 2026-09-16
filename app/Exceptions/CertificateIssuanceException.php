<?php

namespace App\Exceptions;

use Exception;

class CertificateIssuanceException extends Exception
{
    public function __construct(string $message, private readonly int $httpStatus)
    {
        parent::__construct($message);
    }

    public static function alreadyIssued(): self
    {
        return new self('A certificate has already been issued for this request.', 409);
    }

    public static function requestNotReady(): self
    {
        return new self('This request is not ready for release.', 422);
    }

    public static function qrCodeCouldNotBeSaved(): self
    {
        return new self('The certificate QR code could not be generated. No certificate was issued.', 500);
    }

    public static function unsupportedCertificateType(): self
    {
        return new self('This certificate type is not supported.', 422);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
