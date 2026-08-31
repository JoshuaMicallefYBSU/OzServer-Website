<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a rejection be reported back to the controller who asked.
 *
 * reject() used to just delete the row, which left the requester unable to
 * tell a denial from an accept, a cancel, or a stale prune - the request
 * simply vanished from their "Requested By Me" list either way. A rejected
 * request is now kept, flagged, and returned by myRequests() until the
 * requesting plugin has shown it and acknowledged it.
 *
 * The unique(sector_id, requesting_cid) index from the original table means
 * a lingering rejected row would block the requester from ever asking for
 * that sector again, so acknowledge() deletes it and PruneRejectedSector
 * RequestsJob sweeps up any whose requester never came back to collect it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sector_ownership_requests', function (Blueprint $table) {
            // Null = still pending. Set = the owner denied it at this time.
            $table->timestamp('rejected_at')->nullable()->after('target_callsign');
        });
    }

    public function down(): void
    {
        Schema::table('sector_ownership_requests', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
