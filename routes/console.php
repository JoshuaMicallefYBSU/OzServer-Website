<?php

// Nothing scheduled here - OzServer-API owns all of this now, against its own
// Postgres database: stale-row pruning and VATSIM live-data both run on an
// internal timer inside its own process (see its runMaintenance/server.ts),
// and its `npm run dataset:sync` is triggered externally to that service, not
// from here. This website has no domain data of its own left to keep fresh.
