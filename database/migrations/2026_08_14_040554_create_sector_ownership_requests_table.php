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
        Schema::create('sector_ownership_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('requesting_cid');
            $table->string('requesting_callsign');
            // The sector's owner at request time - who must accept/reject.
            $table->unsignedInteger('target_cid');
            $table->string('target_callsign');
            $table->timestamps();

            // One outstanding request per requester per sector.
            $table->unique(['sector_id', 'requesting_cid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sector_ownership_requests');
    }
};
