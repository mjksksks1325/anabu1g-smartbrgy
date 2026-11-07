<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidence photographs staff attach to a certificate (the Probation certificate's community-service page).
     * Files live on the private disk under random names; a row stays a draft for its resident until a certificate
     * is issued with it, after which it belongs to that certificate and can no longer be removed.
     */
    public function up(): void
    {
        Schema::create('certificate_evidence', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('certificate_type');
            $table->foreignId('resident_id')->constrained('residents')->cascadeOnDelete();
            $table->foreignId('issued_certificate_id')->nullable()->constrained('issued_certificates')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->char('sha256', 64);
            $table->timestamps();

            $table->index(['resident_id', 'certificate_type', 'issued_certificate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_evidence');
    }
};
