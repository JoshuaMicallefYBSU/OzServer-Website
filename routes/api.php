<?php

// Nothing routed here - the plugin and the /ops map both talk to OzServer-API
// (config('services.ozserver_api.url')) directly now, not to this website.
// This file is kept only because bootstrap/app.php's withRouting() expects it
// to exist.
