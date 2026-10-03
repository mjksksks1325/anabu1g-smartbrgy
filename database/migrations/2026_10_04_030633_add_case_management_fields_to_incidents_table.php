<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('complainant_resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            $table->foreignId('respondent_resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
        });
        Schema::table('administrative_audits', fn (Blueprint $table) => $table->json('changed_fields')->nullable());
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('complainant_resident_id');
            $table->dropConstrainedForeignId('respondent_resident_id');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('remarks');
        });
        Schema::table('administrative_audits', fn (Blueprint $table) => $table->dropColumn('changed_fields'));
    }
};
