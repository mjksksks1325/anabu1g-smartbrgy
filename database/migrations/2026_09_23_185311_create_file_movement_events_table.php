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
        Schema::create('file_movement_events', function (Blueprint $table) {
            $table->id();
            $table->string('file_reference');
            $table->string('file_name')->nullable();
            $table->string('rfid_tag')->nullable();
            $table->foreignId('cabinet_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drawer_reference')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 20);
            $table->timestamp('occurred_at');
            $table->string('device_event_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['file_reference', 'occurred_at', 'id']);
            $table->index(['action', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_movement_events');
    }
};
