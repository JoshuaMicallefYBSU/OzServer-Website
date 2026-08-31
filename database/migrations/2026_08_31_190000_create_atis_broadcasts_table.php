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
        Schema::create('atis_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('icao', 4)->unique();
            $table->string('atis_letter', 1);
            $table->json('content'); // field name (WIND, VIS, QNH, ...) -> value, as broadcast
            $table->unsignedInteger('frequency')->nullable(); // FSD frequency, e.g. 32500 for 132.500

            $table->timestamp('last_seen_at'); // last plugin push; PruneStaleAtisJob drops rows 90 minutes stale
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atis_broadcasts');
    }
};
