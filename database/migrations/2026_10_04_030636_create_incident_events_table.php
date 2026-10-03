<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('previous_status', 30)->nullable();
            $table->string('status', 30);
            $table->json('changed_fields');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_events');
    }
};
