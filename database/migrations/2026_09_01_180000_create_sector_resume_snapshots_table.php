<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a controller was holding when they left, so reconnecting shortly
 * afterwards puts them straight back to it.
 *
 * An ungraceful disconnect never needed this - ownership is simply retained
 * for SectorOwnership::DISCONNECT_GRACE_MINUTES and is still there when they
 * return. A *graceful* one did: releaseAll deletes the rows outright, which
 * is the whole point (nobody should be blocked from a position someone
 * deliberately left), but it also meant coming back two minutes later left
 * them with nothing.
 *
 * So the rows are still released - anyone may take them meanwhile - and this
 * records what they were, letting resume() re-claim whatever is *still free*
 * for the same cid on the same callsign. Anything somebody else picked up in
 * the interim stays theirs; this never takes a sector back off a live
 * controller.
 *
 * Keyed on cid + callsign together, not cid alone: coming back on a
 * different position is a different session, and should not inherit the
 * sectors of the one before it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_resume_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('controller_cid');
            $table->string('controller_callsign');
            // Sector names held at the moment of disconnect.
            $table->json('sectors');
            // Callsigns of the flights whose datalink authority was theirs, so
            // the tags come back with the sectors.
            $table->json('flights');
            $table->timestamps();

            $table->unique(['controller_cid', 'controller_callsign']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_resume_snapshots');
    }
};
