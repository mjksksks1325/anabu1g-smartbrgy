<?php

namespace App\Http\Controllers;

use App\Models\IssuedCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    public function show(Request $request, string $code): JsonResponse|View
    {
        $certificate = IssuedCertificate::query()
            ->where('verification_code', Str::upper(trim($code)))
            ->firstOrFail();

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
                ],
            ]);
        }

        return view('certificate.verify', ['certificate' => $certificate]);
    }
}
