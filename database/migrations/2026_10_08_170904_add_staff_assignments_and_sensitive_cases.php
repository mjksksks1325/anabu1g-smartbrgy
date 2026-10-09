<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('staff_permissions')->nullable();
        });
        Schema::table('incidents', function (Blueprint $table): void {
            $table->boolean('is_sensitive')->default(false);
        });
        Schema::table('administrative_audits', function (Blueprint $table): void {
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->json('before_assignments')->nullable();
            $table->json('after_assignments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('staff_permissions'));
        Schema::table('incidents', fn (Blueprint $table) => $table->dropColumn('is_sensitive'));
        Schema::table('administrative_audits', fn (Blueprint $table) => $table->dropColumn(['target_user_id', 'before_assignments', 'after_assignments']));
    }
};
