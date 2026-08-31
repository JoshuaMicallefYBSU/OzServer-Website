<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an ungraceful disconnect keep its sectors for a grace period.
 *
 * A controller who closes vatSys or presses Disconnect gives their sectors
 * up immediately - the plugin says so explicitly (SectorOwnershipController
 * ::releaseAll). A controller whose client crashes or whose internet drops
 * says nothing at all, and the only thing distinguishing the two is the
 * absence of that message. So ownership is retained for a while after they
 * stop appearing in the VATSIM datafeed, and reconnecting inside that window
 * puts them straight back where they were.
 *
 * ReleaseStaleSectorOwnershipsJob stamps this every pass the owner is seen
 * online, and only reaps once it has gone stale. That also subsumes the
 * separate created_at grace that job used to apply: a freshly claimed sector
 * is stamped at creation, so it is protected from the datafeed's own lag in
 * showing a brand-new connection without needing a second rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sector_ownerships', function (Blueprint $table) {
            $table->timestamp('last_seen_online_at')->nullable()->after('controller_callsign');
        });

        // Existing rows have never been stamped; treat them as seen when they
        // were claimed rather than as indefinitely stale.
        DB::table('sector_ownerships')->whereNull('last_seen_online_at')->update([
            'last_seen_online_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sector_ownerships', function (Blueprint $table) {
            $table->dropColumn('last_seen_online_at');
        });
    }
};
