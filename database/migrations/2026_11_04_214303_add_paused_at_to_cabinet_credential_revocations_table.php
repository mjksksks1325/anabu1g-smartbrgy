<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cabinet_credential_revocations', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('cabinet_credential_revocations')->whereNotNull('paused_at')->exists()) {
            throw new LogicException('Release held revocations explicitly before removing their pause state.');
        }
        Schema::table('cabinet_credential_revocations', function (Blueprint $table) {
            $table->dropColumn('paused_at');
        });
    }
};
