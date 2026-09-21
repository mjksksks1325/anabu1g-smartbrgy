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
        Schema::create('voter_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->unique()->constrained()->restrictOnDelete();
            $table->string('voter_number', 40)->unique();
            $table->text('comelec_voter_number');
            $table->char('comelec_voter_number_hash', 64)->unique();
            $table->string('precinct_number', 50);
            $table->string('cluster_number', 50);
            $table->date('registration_date');
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->char('integrity_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'registration_date']);
            $table->index(['precinct_number', 'cluster_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voter_registrations');
    }
};
