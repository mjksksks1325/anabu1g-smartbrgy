<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_protection_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->foreignId('protected_resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            $table->foreignId('respondent_resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            $table->string('protected_person');
            $table->string('respondent');
            $table->date('issued_on');
            $table->date('effective_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('recorded');
            $table->string('issuing_authority');
            $table->text('internal_remarks')->nullable();
            $table->string('supporting_document_reference')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_protection_orders');
    }
};
