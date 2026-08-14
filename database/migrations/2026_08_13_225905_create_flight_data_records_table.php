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
        Schema::create('flight_data_records', function (Blueprint $table) {
            $table->id();
            $table->string('callsign')->unique();
            $table->string('state')->nullable(); // mirrors FDP2.FDR.FDRStates, kept as free string (exact enum names unconfirmed)

            $table->string('flight_rules', 1)->nullable(); // I/V/Y/Z
            $table->string('aircraft_type')->nullable();
            $table->string('aircraft_wake', 1)->nullable();
            $table->string('aircraft_equip')->nullable();
            $table->string('aircraft_surv_equip')->nullable();
            $table->unsignedSmallInteger('aircraft_count')->default(1);

            $table->string('dep_airport', 4)->nullable()->index();
            $table->string('des_airport', 4)->nullable()->index();
            $table->text('route')->nullable();
            $table->json('parsed_route')->nullable();
            $table->string('sid_star_string')->nullable();
            $table->string('runway_string')->nullable();
            $table->string('departure_runway')->nullable();

            $table->unsignedInteger('rfl')->nullable();
            $table->unsignedInteger('cfl_lower')->nullable();
            $table->unsignedInteger('cfl_upper')->nullable();
            $table->smallInteger('assigned_ssr_code')->nullable(); // null when vatSys reports -1

            $table->timestamp('atd')->nullable();
            $table->timestamp('etd')->nullable();
            $table->unsignedInteger('eet_minutes')->nullable();
            $table->unsignedInteger('tas')->nullable();

            $table->boolean('text_only')->default(false);
            $table->boolean('receive_only')->default(false);
            $table->string('label_op_data')->nullable(); // scratchpad
            $table->text('remarks')->nullable();

            // Datalink authority - who currently owns push/pull rights for this flight.
            // No FK yet: Position/Sector tables don't exist until phase 2.
            $table->unsignedInteger('controlling_cid')->nullable()->index();
            $table->string('controlling_callsign')->nullable();

            $table->timestamp('last_seen_at')->nullable(); // last plugin push; basis for a future staleness-prune job
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_data_records');
    }
};
