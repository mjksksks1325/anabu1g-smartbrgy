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
        Schema::table('employee_cabinet_access', function (Blueprint $table) {
            $table->string('rpi_employee_id', 32)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_cabinet_access', function (Blueprint $table) {
            $table->dropUnique(['rpi_employee_id']);
            $table->dropColumn('rpi_employee_id');
        });
    }
};
