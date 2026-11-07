<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `template_version` records which certificate templates a certificate was issued with, so a reprint uses the
     * same wording. Certificates issued before the official 2026 templates keep null (the earlier layout).
     * Revocation keeps the record and adds who revoked it, when and why.
     */
    public function up(): void
    {
        Schema::table('issued_certificates', function (Blueprint $table): void {
            $table->string('template_version', 20)->nullable()->after('certificate_type');
            $table->timestamp('revoked_at')->nullable()->after('expires_on');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable()->after('revoked_by');
        });

        // Certificates issued with the official 2026 templates (Work Group 6) are the only ones whose details
        // record the resident's name parts.
        DB::table('issued_certificates')
            ->whereNotNull('resident_snapshot')
            ->where('resident_snapshot', 'like', '%"first_name"%')
            ->update(['template_version' => '2026.11']);
    }

    public function down(): void
    {
        Schema::table('issued_certificates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['template_version', 'revoked_at', 'revocation_reason']);
        });
    }
};
