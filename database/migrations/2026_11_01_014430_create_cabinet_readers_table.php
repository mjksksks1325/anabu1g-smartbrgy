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
        Schema::create('cabinet_readers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabinet_device_id')->constrained()->cascadeOnDelete();
            $table->string('reader_identifier', 80)->unique();
            $table->string('model', 40)->default('YRM100');
            $table->string('drawer_reference', 20);
            $table->boolean('is_active')->default(true);
            $table->unique(['cabinet_device_id', 'drawer_reference']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabinet_readers');
    }
};
