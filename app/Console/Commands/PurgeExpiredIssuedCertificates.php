<?php

namespace App\Console\Commands;

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

        IssuedCertificate::query()->chunkById(100, function (Collection $certificates) use (&$purgedCount, $dryRun): void {
            foreach ($certificates as $certificate) {
                if (! $certificate->isExpired()) {
                    continue;
                }

                if ($dryRun) {
                    $purgedCount++;

                    continue;
                }

                if (! $certificate->delete()) {
                    continue;
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
        $qrPath = $this->qrStoragePath($certificate->qr_code_path);
        if ($qrPath !== null) {
            Storage::disk('public')->delete($qrPath);
        }

        $photoPath = $certificate->photo_path;
        if ($photoPath !== null && str_starts_with($photoPath, 'certificate-photos/')) {
            Storage::disk('local')->delete($photoPath);
        }
    }

    private function qrStoragePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return str_starts_with($path, 'qrcodes/') ? $path : null;
    }
}
