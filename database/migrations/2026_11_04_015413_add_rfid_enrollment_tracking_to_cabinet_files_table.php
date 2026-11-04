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
        Schema::table('cabinet_files', function (Blueprint $table): void {
            $table->uuid('rfid_enrollment_request_id')->nullable()->unique();
            $table->timestamp('rfid_enrollment_requested_at')->nullable();
            $table->timestamp('rfid_enrollment_confirmed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cabinet_files', function (Blueprint $table): void {
            $table->dropUnique(['rfid_enrollment_request_id']);
            $table->dropColumn([
                'rfid_enrollment_request_id',
                'rfid_enrollment_requested_at',
                'rfid_enrollment_confirmed_at',
            ]);
        });
    }
};
