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
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('full_name');
            $table->string('frequency')->nullable();
            $table->string('callsign')->nullable();
            $table->boolean('display_in_sectors_window')->default(true);
            $table->boolean('display_in_handoff_window')->default(true);
            $table->json('responsible_sectors')->nullable(); // list of sector name strings, informational only
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
