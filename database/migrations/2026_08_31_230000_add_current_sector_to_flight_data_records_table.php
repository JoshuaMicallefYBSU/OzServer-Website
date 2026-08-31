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
        Schema::table('flight_data_records', function (Blueprint $table) {
            // The geographic sector this aircraft is physically inside of right now, resolved by
            // the plugin against every sector it knows about - not just ones with an active
            // SectorOwnership row. Deliberately separate from controlling_cid/controlling_callsign
            // above (who owns the tag): this is which airspace the aircraft is actually in, whether
            // or not anyone has claimed it. No FK to sectors - the plugin sends a name that may not
            // (yet) exist in this table, same as controlling_callsign already assumes elsewhere.
            $table->string('current_sector')->nullable()->after('controlling_callsign');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_data_records', function (Blueprint $table) {
            $table->dropColumn('current_sector');
        });
    }
};
