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
        Schema::create('cabinet_file_enrollment_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('cabinet_file_id')->constrained()->restrictOnDelete();
            $table->foreignId('cabinet_device_id')->constrained()->restrictOnDelete();
            $table->foreignId('cabinet_reader_id')->constrained()->restrictOnDelete();
            $table->string('reader_identifier', 80);
            $table->json('folder_snapshot');
            $table->string('status', 20)->default('pending');
            $table->string('rfid_epc', 62)->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['cabinet_device_id', 'status', 'expires_at'], 'cabinet_enrollment_pending_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabinet_file_enrollment_requests');
    }
};
