<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('issued_certificates')
            ->whereNotNull('qr_code_path')
            ->orderBy('id')
            ->chunkById(100, function (Collection $certificates): void {
                foreach ($certificates as $certificate) {
                    $storedPath = (string) $certificate->qr_code_path;
                    $relativePath = parse_url($storedPath, PHP_URL_PATH);

                    if (! is_string($relativePath) || ! str_starts_with($relativePath, '/storage/qrcodes/')) {
                        continue;
                    }

                    if ($storedPath === $relativePath) {
                        continue;
                    }

                    DB::table('issued_certificates')
                        ->where('id', $certificate->id)
                        ->update(['qr_code_path' => $relativePath]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Original hosts cannot be reconstructed safely after paths are normalized.
    }
};
