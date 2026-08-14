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
        Schema::create('runways', function (Blueprint $table) {
            $table->id();
            $table->string('airport_icao', 4)->index();
            $table->string('name', 3);
            $table->string('data_runway', 3);
            $table->unique(['airport_icao', 'name']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runways');
    }
};
