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
        Schema::create('cabinet_credential_revocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('cabinet_device_id')->constrained()->restrictOnDelete();
            $table->string('rpi_employee_id', 32);
            $table->boolean('remove_rfid')->default(true);
            $table->boolean('remove_face')->default(true);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['cabinet_device_id', 'acknowledged_at', 'id'], 'cabinet_revocations_pending_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabinet_credential_revocations');
    }
};
