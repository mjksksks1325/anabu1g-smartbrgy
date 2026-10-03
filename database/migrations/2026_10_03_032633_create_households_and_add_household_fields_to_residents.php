<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('household_number_sequences', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        DB::table('household_number_sequences')->insert(['id' => 1, 'last_number' => 0]);

        Schema::create('households', function (Blueprint $table): void {
            $table->id();
            $table->string('household_number', 100)->unique();
            $table->text('address');
            $table->foreignId('purok_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('household_head_resident_id')->nullable()->unique()->constrained('residents')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('residents', function (Blueprint $table): void {
            $table->foreignId('household_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('relationship_to_household_head', 100)->nullable();
            $table->boolean('is_household_head')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('household_id');
            $table->dropColumn(['relationship_to_household_head', 'is_household_head']);
        });
        Schema::dropIfExists('households');
        Schema::dropIfExists('household_number_sequences');
    }
};
