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

        if ($certificate->isExpired()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'expired',
                    'message' => 'This certificate has expired.',
                ], 410);
            }

            return response()->view('certificate.verify', [
                'certificate' => $certificate,
                'isExpired' => true,
            ], 410);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'certificate' => [
                    'certificate_number' => $certificate->certificate_number,
                    'verification_code' => $certificate->verification_code,
                    'certificate_type' => $certificate->certificate_type,
                    'resident_name' => $certificate->resident_name,
                    'purpose' => $certificate->purpose,
                    'issued_at' => $certificate->issued_at?->toIso8601String(),
                    'issued_by' => $certificate->issued_by,
                    'expires_on' => $certificate->validUntil()?->toDateString(),
                ],
            ]);
        }

        return view('certificate.verify', ['certificate' => $certificate, 'isExpired' => false]);
    }
}
