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
        Schema::table('document_requests', function (Blueprint $table) {
            $table->foreignId('resident_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('issued_certificates', function (Blueprint $table) {
            $table->foreignId('resident_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issued_certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resident_id');
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resident_id');
        });
    }
};
