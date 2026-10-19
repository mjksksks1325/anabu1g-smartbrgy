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
        Schema::table('employee_cabinet_access', function (Blueprint $table): void {
            $table->string('drawer_access', 20)->default('none');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_cabinet_access', function (Blueprint $table): void {
            $table->dropColumn('drawer_access');
        });
    }
};
