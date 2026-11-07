<?php

namespace App\Console\Commands;

use App\Models\CertificateEvidence;
use App\Models\IssuedCertificate;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredIssuedCertificates extends Command
{
    protected $signature = 'certificates:purge-expired {--dry-run : Report expired certificates without deleting them}';

    protected $description = 'Delete expired issued certificates and their stored QR/photo files while preserving request history';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $purgedCount = 0;

        // Revoked certificates are kept as a record of the revocation.
        IssuedCertificate::query()->whereNull('revoked_at')->chunkById(100, function (Collection $certificates) use (&$purgedCount, $dryRun): void {
            foreach ($certificates as $certificate) {
                if (! $certificate->isExpired()) {
                    continue;
                }

                if ($dryRun) {
                    $purgedCount++;

                    continue;
                }

                // Its evidence photographs go with it, so they never fall back to being drafts.
                $evidence = CertificateEvidence::query()->where('issued_certificate_id', $certificate->id)->get();
                if (! $certificate->delete()) {
                    continue;
                }
                foreach ($evidence as $photograph) {
                    $photograph->delete();
                    Storage::disk('local')->delete($photograph->path);
                }

                $purgedCount++;
                $this->deleteCertificateFiles($certificate);
            }
        });

        $this->info($dryRun
            ? 'Expired certificates that would be deleted: '.$purgedCount
            : 'Expired certificates deleted: '.$purgedCount);

        return self::SUCCESS;
    }

    private function deleteCertificateFiles(IssuedCertificate $certificate): void
    {
        $qrPath = $certificate->qrStoragePath();
        if ($qrPath !== null) {
            Storage::disk('public')->delete($qrPath);
        }

        $photoPath = $certificate->photo_path;
        if ($photoPath !== null && str_starts_with($photoPath, 'certificate-photos/')) {
            Storage::disk('local')->delete($photoPath);
        }
    }
}
