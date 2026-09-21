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
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('resident_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 20)->nullable();
            $table->date('date_of_birth');
            $table->string('gender', 20);
            $table->string('civil_status', 30);
            $table->string('purok', 100);
            $table->text('address');
            $table->string('contact_number', 20)->nullable();
            $table->string('residency_type', 30);
            $table->json('special_groups')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_in_good_standing')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index(['date_of_birth', 'status']);
            $table->index('purok');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
