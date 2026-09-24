<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->string('private_attachment_path')->nullable();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('resident_id')->nullable()->unique()->constrained('residents')->restrictOnDelete();
        });
        Schema::table('residents', function (Blueprint $table): void {
            $table->string('portal_registration_hash', 64)->nullable();
            $table->timestamp('portal_registration_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table): void {
            $table->dropColumn('private_attachment_path');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['resident_id']);
            $table->dropUnique(['resident_id']);
            $table->dropColumn('resident_id');
        });
        Schema::table('residents', function (Blueprint $table): void {
            $table->dropColumn(['portal_registration_hash', 'portal_registration_expires_at']);
        });
    }
};
