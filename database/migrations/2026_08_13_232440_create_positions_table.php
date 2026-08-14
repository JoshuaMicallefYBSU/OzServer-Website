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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('group'); // Class D / Procedural / Enroute / Oceanic / TCU
            $table->string('name')->unique();
            $table->string('type'); // ASMGCS / ASD
            $table->decimal('default_lat', 10, 6);
            $table->decimal('default_lon', 10, 6);
            $table->decimal('default_range', 8, 2)->nullable();
            $table->decimal('magnetic_variation', 5, 2)->nullable();
            $table->decimal('rotation', 6, 2)->nullable();
            $table->string('asmgcs_airport', 4)->nullable();
            $table->decimal('visibility_range', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
