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
        Schema::create('cabinet_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_reference', 100)->unique();
            $table->string('file_name', 255);
            $table->foreignId('cabinet_device_id')->constrained()->restrictOnDelete();
            $table->string('drawer_reference', 20);
            $table->string('rfid_epc', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->index(['cabinet_device_id', 'drawer_reference', 'is_active']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabinet_files');
    }
};
