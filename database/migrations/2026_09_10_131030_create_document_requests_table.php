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
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();

            $table->string('reference_code')->unique();

            $table->string('document_type');

            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->text('address');
            $table->string('contact_number')->nullable();

            $table->string('purpose')->nullable();
            $table->string('business_name')->nullable();

            $table->string('attachment_path')->nullable();

            $table->enum('status', [
                'pending',
                'processing',
                'approved',
                'ready_for_release',
                'released',
                'rejected'
            ])->default('pending');

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
