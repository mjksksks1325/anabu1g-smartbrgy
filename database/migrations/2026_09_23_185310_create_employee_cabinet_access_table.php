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
        Schema::create('employee_cabinet_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->string('rfid_enrollment_status', 20)->default('not_started');
            $table->string('face_enrollment_status', 20)->default('not_started');
            $table->timestamp('rfid_enrolled_at')->nullable();
            $table->timestamp('face_enrolled_at')->nullable();
            $table->unsignedBigInteger('authorization_version')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_cabinet_access');
    }
};
