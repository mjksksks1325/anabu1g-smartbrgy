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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number', 40)->unique();
            $table->string('incident_type', 100);
            $table->dateTime('occurred_at');
            $table->string('location', 255);
            $table->string('complainant_name')->nullable();
            $table->string('respondent_name')->nullable();
            $table->string('severity', 20)->default('medium');
            $table->text('details');
            $table->string('status', 30)->default('pending');
            $table->text('resolution_notes')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
