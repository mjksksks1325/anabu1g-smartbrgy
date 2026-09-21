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
        Schema::create('puroks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 7)->default('#22C55E');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('puroks')->insert([
            ['name' => 'Purok 1 - Sampaguita', 'color' => '#22C55E', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Purok 2 - Rosal', 'color' => '#60A5FA', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Purok 3 - Camia', 'color' => '#F59E0B', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Purok 4 - Ilang-Ilang', 'color' => '#A78BFA', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Purok 5 - Mabini', 'color' => '#34D399', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('puroks');
    }
};
