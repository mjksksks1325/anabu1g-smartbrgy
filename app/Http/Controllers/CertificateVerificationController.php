<?php

namespace App\Http\Controllers;

use App\Models\IssuedCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    public function show(Request $request, string $code): JsonResponse|Response|View
    {
        $certificate = IssuedCertificate::query()
            ->where('verification_code', Str::upper(trim($code)))
            ->firstOrFail();

        if ($certificate->isRevoked()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'revoked',
                    'message' => 'This certificate has been revoked by Barangay Anabu I-G.',
                    'certificate' => [
                        'certificate_number' => $certificate->certificate_number,
                        'certificate_type' => $certificate->certificate_type,
                        'revoked_on' => $certificate->revoked_at?->toDateString(),
                    ],
                ], 410);
            }

            return response()->view('certificate.verify', ['certificate' => $certificate, 'state' => 'revoked'], 410);
        }

        if ($certificate->isExpired()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'expired',
                    'message' => 'This certificate has expired.',
                ], 410);
            }

            return response()->view('certificate.verify', ['certificate' => $certificate, 'state' => 'expired'], 410);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'valid',
                'certificate' => [
                    'certificate_number' => $certificate->certificate_number,
                    'certificate_type' => $certificate->certificate_type,
                    'holder_initials' => $certificate->holderInitials(),
                    'issued_on' => $certificate->issued_at?->toDateString(),
                    'expires_on' => $certificate->validUntil()?->toDateString(),
                ],
            ]);
        }

        return view('certificate.verify', ['certificate' => $certificate, 'state' => 'valid']);
    }
}
