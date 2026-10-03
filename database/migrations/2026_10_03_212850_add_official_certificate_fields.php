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
        Schema::table('residents', function (Blueprint $table): void {
            $table->string('nationality', 100)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_verified_indigent')->default(false);
        });
        Schema::table('issued_certificates', function (Blueprint $table): void {
            $table->json('resident_snapshot')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('photo_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issued_certificates', function (Blueprint $table): void {
            $table->dropColumn(['resident_snapshot', 'expires_on', 'photo_path']);
        });
        Schema::table('residents', function (Blueprint $table): void {
            $table->dropColumn(['nationality', 'photo_path', 'is_verified_indigent']);
        });
    }
};
