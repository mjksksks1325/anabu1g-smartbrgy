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
        Schema::table('document_requests', function (Blueprint $table) {
            $table->string('email')->nullable()->after('contact_number');
        });

        DB::table('document_requests')
            ->whereNull('email')
            ->whereNotNull('contact_number')
            ->where('contact_number', 'like', '%@%')
            ->update([
                'email' => DB::raw('contact_number'),
                'contact_number' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('document_requests')
            ->whereNull('contact_number')
            ->whereNotNull('email')
            ->update([
                'contact_number' => DB::raw('email'),
            ]);

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
