<?php

namespace App\Console\Commands;

use App\Models\CertificateEvidence;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeDraftCertificateEvidence extends Command
{
    protected $signature = 'certificates:purge-draft-evidence {--days=14 : Remove draft photographs older than this many days}';

    protected $description = 'Delete evidence photographs that were uploaded but never issued with a certificate';

    public function handle(): int
    {
        $count = 0;

        CertificateEvidence::query()->whereNull('issued_certificate_id')
            ->where('created_at', '<', now()->subDays(max(1, (int) $this->option('days'))))
            ->chunkById(100, function (Collection $drafts) use (&$count): void {
                foreach ($drafts as $draft) {
                    DB::transaction(function () use ($draft, &$count): void {
                        $evidence = CertificateEvidence::query()->lockForUpdate()->find($draft->id);
                        if ($evidence === null || ! $evidence->isDraft()) {
                            return;
                        }
                        $evidence->delete();
                        Storage::disk('local')->delete($evidence->path);
                        $count++;
                    });
                }
            });

        $this->info('Draft evidence photographs deleted: '.$count);

        return self::SUCCESS;
    }
}
