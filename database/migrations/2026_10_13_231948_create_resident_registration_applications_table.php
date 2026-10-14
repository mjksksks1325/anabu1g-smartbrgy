<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_registration_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('identity_key', 64)->nullable()->unique();
            $table->string('email_key', 64)->nullable()->unique();
            $table->json('details');
            $table->string('email');
            $table->string('photo_path');
            $table->string('locale', 3)->default('en');
            $table->string('status', 30)->default('pending')->index();
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_registration_applications');
    }
};
