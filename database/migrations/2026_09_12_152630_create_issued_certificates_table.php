<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('issued_certificates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_request_id')
                ->nullable()
                ->constrained('document_requests')
                ->nullOnDelete();

            $table->string('certificate_number')->unique();
            $table->string('verification_code')->unique();

            $table->string('certificate_type');
            $table->string('resident_name');
            $table->string('purpose')->nullable();

            $table->decimal('amount_paid', 10, 2)->default(0);

            $table->timestamp('issued_at')->nullable();
            $table->string('issued_by')->nullable();

            $table->string('qr_code_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issued_certificates');
    }
};
