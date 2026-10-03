<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_request_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('restriction_type', 30)->default('document_request');
            $table->string('affected_document_type')->nullable();
            $table->string('reason_category', 100);
            $table->text('internal_reason');
            $table->string('resident_visible_reason')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('next_review_at')->nullable();
            $table->string('status', 30)->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('lifted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('lifted_at')->nullable();
            $table->text('lift_reason')->nullable();
            $table->timestamps();
            $table->index(['resident_id', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_request_restrictions');
    }
};
