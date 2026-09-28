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
        Schema::table('administrative_audits', function (Blueprint $table) {
            $table->string('device_event_id')->nullable()->unique();
            $table->string('cabinet_identifier')->nullable();
            $table->string('rpi_employee_id', 32)->nullable();
            $table->string('access_result', 20)->nullable();
            $table->string('authentication_method', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('administrative_audits', function (Blueprint $table) {
            $table->dropUnique(['device_event_id']);
            $table->dropColumn(['device_event_id', 'cabinet_identifier', 'rpi_employee_id', 'access_result', 'authentication_method']);
        });
    }
};
