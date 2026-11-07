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
        return new self('This request is not ready for release yet. On the Certificates board, use "Mark ready" (or set its status to Ready for Release), then issue it.', 422);
    }

    public static function qrCodeCouldNotBeSaved(): self
    {
        return new self('The certificate QR code could not be generated. No certificate was issued.', 500);
    }

    public static function unsupportedCertificateType(): self
    {
        return new self('This certificate type is not supported.', 422);
    }

    public static function residentIsNotEligible(string $reason): self
    {
        return new self($reason, 422);
    }

    /**
     * @param  list<string>  $missingDetails  Labels of the details the printed certificate needs.
     */
    public static function incompleteDetails(array $missingDetails, ?string $hint = null): self
    {
        return new self(trim('Complete these details before issuing this certificate: '.implode(', ', $missingDetails).'. '.$hint), 422);
    }

    public static function notApproved(string $certificateType): self
    {
        return new self("Issuing the {$certificateType} is turned off until the barangay approves its fee and requirements. You can still complete and preview it.", 403);
    }

    public static function invalidDetail(string $message): self
    {
        return new self($message, 422);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
