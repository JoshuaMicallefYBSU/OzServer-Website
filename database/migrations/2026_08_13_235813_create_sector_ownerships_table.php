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
        Schema::create('sector_ownerships', function (Blueprint $table) {
            $table->id();
            // One row = current owner (claim inserts, release deletes) -
            // this is current state, not a history log.
            $table->foreignId('sector_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('controller_cid');
            $table->string('controller_callsign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sector_ownerships');
    }
};
