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
    /**
     * Named explicitly because the name Laravel would generate,
     * sector_resume_snapshots_controller_cid_controller_callsign_unique, is 65
     * characters - one over MySQL's 64-character identifier limit, which
     * rejects it outright.
     *
     * That was invisible locally: the test suite runs on sqlite (see
     * phpunit.xml), which has no such limit, so this migration passed
     * everywhere except the one place it mattered.
     */
    private const UNIQUE_INDEX = 'sector_resume_snapshots_cid_callsign_unique';

    public function up(): void
    {
        // Deliberately not a bare Schema::create, and the index is added
        // separately rather than inside the closure.
        //
        // Laravel compiles a Blueprint into several statements: CREATE TABLE
        // first, then ALTER TABLE ... ADD UNIQUE. MySQL DDL is not
        // transactional, so the CREATE commits the moment it runs. When the
        // ALTER then failed on the over-long index name above, the table was
        // left behind while the migration was never recorded as run - and every
        // later attempt died on "Base table or view already exists", reporting
        // the wreckage instead of the actual cause.
        //
        // Guarding both steps means this migration can simply be re-run against
        // a database left in that state, and finishes the job rather than
        // needing the table dropped or the migrations table edited by hand.
        if (! Schema::hasTable('sector_resume_snapshots')) {
            Schema::create('sector_resume_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('controller_cid');
                $table->string('controller_callsign');
                // Sector names held at the moment of disconnect.
                $table->json('sectors');
                // Callsigns of the flights whose datalink authority was theirs,
                // so the tags come back with the sectors.
                $table->json('flights');
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('sector_resume_snapshots', self::UNIQUE_INDEX)) {
            Schema::table('sector_resume_snapshots', function (Blueprint $table) {
                $table->unique(['controller_cid', 'controller_callsign'], self::UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_resume_snapshots');
    }
};
