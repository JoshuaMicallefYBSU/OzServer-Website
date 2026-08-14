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
        Schema::create('waypoints', function (Blueprint $table) {
            $table->id();
            // Not unique: ~36 of ~9,500 identifiers collide in the source
            // data (duplicate Fix/Navaid idents), so this is a full
            // delete-and-reinsert table each sync, not upsert-by-name.
            $table->string('name')->index();
            $table->string('type')->nullable(); // Fix / Navaid
            $table->decimal('lat', 10, 6);
            $table->decimal('lon', 10, 6);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waypoints');
    }
};
