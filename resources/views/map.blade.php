<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OzServer | Ops</title>
        <meta name="mapbox-token" content="{{ config('services.mapbox.token') }}">
        <link href="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.css" rel="stylesheet">
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.9.0/mapbox-gl.js"></script>
        <style>
            :root {
                --bg: rgba(2, 6, 23, 0.9);
                --line: rgba(255, 255, 255, 0.09);
                --text: #e2e8f0;
                --muted: #94a3b8;
                --faint: #64748b;
                --accent: #22d3ee;
                --warn: #fb923c;
                --bad: #f87171;
                --good: #4ade80;
                --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            }
            * { box-sizing: border-box; }
            html, body { margin: 0; height: 100%; background: #020617; color: var(--text); font-family: var(--sans); font-size: 13px; }
            #map { position: absolute; inset: 0; }
            button { font: inherit; color: inherit; }

            .oz-panel {
                position: absolute; z-index: 2; background: var(--bg);
                border: 1px solid var(--line); border-radius: 12px;
                backdrop-filter: blur(6px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            }

            /* ---------- top bar ---------- */
            .oz-topbar { top: 12px; left: 12px; right: 12px; height: 48px; display: flex; align-items: center; gap: 16px; padding: 0 14px; }
            .oz-brand { font-weight: 700; letter-spacing: 0.04em; white-space: nowrap; }
            .oz-brand span { color: var(--accent); }
            .oz-topbar select {
                background: #0f172a; color: var(--text); border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 6px; padding: 4px 8px; font: inherit;
            }
            .oz-stats { display: flex; gap: 14px; flex: 1; overflow: hidden; }
            .oz-stat { display: flex; flex-direction: column; line-height: 1.1; white-space: nowrap; }
            .oz-stat b { font-size: 15px; font-family: var(--mono); }
            .oz-stat small { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
            .oz-stat.warn b { color: var(--warn); }
            .oz-btn {
                background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 6px; padding: 5px 10px;
                cursor: pointer; white-space: nowrap; font-size: 12px;
            }
            .oz-btn:hover { border-color: rgba(255, 255, 255, 0.35); }
            .oz-btn.on { background: rgba(34, 211, 238, 0.15); border-color: rgba(34, 211, 238, 0.6); color: var(--accent); }
            .oz-live { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: 11px; white-space: nowrap; }
            .oz-live i { width: 8px; height: 8px; border-radius: 50%; background: var(--faint); display: inline-block; }
            .oz-live.on i { background: var(--good); box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.2); }

            /* ---------- sidebar ---------- */
            .oz-sidebar { top: 72px; left: 12px; bottom: 12px; width: 340px; display: flex; flex-direction: column; overflow: hidden; }
            .oz-tabs { display: flex; border-bottom: 1px solid var(--line); }
            .oz-tabs button {
                flex: 1; background: none; border: 0; padding: 10px 4px; cursor: pointer; color: var(--muted);
                font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 2px solid transparent;
            }
            .oz-tabs button.active { color: var(--text); border-bottom-color: var(--accent); }
            .oz-tabs .oz-badge { margin-left: 4px; }
            .oz-tab-body { flex: 1; overflow-y: auto; padding: 8px; }
            .oz-tab-body[hidden] { display: none; }
            .oz-search {
                width: 100%; margin-bottom: 8px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.12);
                color: var(--text); border-radius: 6px; padding: 6px 8px; font: inherit;
            }
            .oz-section-title { margin: 12px 4px 6px; font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
            .oz-empty { color: var(--faint); padding: 8px 4px; }

            .oz-row {
                display: flex; align-items: center; gap: 8px; width: 100%; text-align: left;
                background: none; border: 1px solid transparent; border-radius: 8px; padding: 7px 8px; cursor: pointer;
            }
            .oz-row:hover { background: rgba(255, 255, 255, 0.04); }
            .oz-row.selected { background: rgba(34, 211, 238, 0.08); border-color: rgba(34, 211, 238, 0.35); }
            .oz-row .oz-main { flex: 1; min-width: 0; }
            .oz-row .oz-title { font-family: var(--mono); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .oz-row .oz-sub { color: var(--muted); font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .oz-row .oz-right { color: var(--muted); font-family: var(--mono); font-size: 11px; text-align: right; white-space: nowrap; }

            .oz-swatch { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; background: var(--c, var(--faint)); }
            .oz-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; background: var(--good); }
            .oz-dot.stale { background: var(--warn); }
            .oz-dot.off { background: var(--faint); }

            .oz-badge {
                display: inline-block; font-size: 9px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
                padding: 1px 5px; border-radius: 4px; background: rgba(255, 255, 255, 0.08); color: var(--muted); vertical-align: 1px;
            }
            .oz-badge[hidden] { display: none; }
            .oz-badge.flow { background: rgba(167, 139, 250, 0.18); color: #c4b5fd; }
            .oz-badge.warn { background: rgba(251, 146, 60, 0.18); color: var(--warn); }
            .oz-badge.bad { background: rgba(248, 113, 113, 0.18); color: var(--bad); }
            .oz-badge.good { background: rgba(74, 222, 128, 0.15); color: var(--good); }
            .oz-badge.accent { background: rgba(34, 211, 238, 0.15); color: var(--accent); }

            .oz-chip {
                display: inline-flex; align-items: center; gap: 5px; margin: 2px 3px 2px 0; padding: 2px 7px;
                border-radius: 999px; border: 1px solid color-mix(in srgb, var(--c, #64748b) 60%, transparent);
                background: color-mix(in srgb, var(--c, #64748b) 16%, transparent); cursor: pointer;
                font-family: var(--mono); font-size: 11px; white-space: nowrap;
            }
            .oz-chip:hover { background: color-mix(in srgb, var(--c, #64748b) 30%, transparent); }
            .oz-chip.plain { --c: #64748b; }
            .oz-chip.current { outline: 2px solid #fff; outline-offset: 1px; }

            /* ---------- detail panel ---------- */
            .oz-detail { top: 72px; right: 12px; width: 360px; max-height: calc(100% - 140px); overflow-y: auto; padding: 14px 16px 16px; }
            .oz-detail[hidden] { display: none; }
            .oz-detail-head { display: flex; align-items: flex-start; gap: 10px; }
            .oz-detail-head .oz-swatch { width: 14px; height: 14px; margin-top: 4px; }
            .oz-detail h2 { margin: 0; font-size: 17px; font-family: var(--mono); }
            .oz-detail .oz-kind { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
            .oz-close { margin-left: auto; background: none; border: 0; color: var(--muted); cursor: pointer; font-size: 18px; line-height: 1; }
            .oz-close:hover { color: var(--text); }
            .oz-kv { display: grid; grid-template-columns: 110px 1fr; gap: 4px 10px; margin-top: 12px; }
            .oz-kv dt { color: var(--muted); }
            .oz-kv dd { margin: 0; font-family: var(--mono); word-break: break-word; }
            .oz-block { margin-top: 14px; }
            .oz-block h4 { margin: 0 0 6px; font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
            .oz-note-box { margin-top: 12px; padding: 8px 10px; border-radius: 8px; font-size: 12px; line-height: 1.4; }
            .oz-note-box.flow { background: rgba(167, 139, 250, 0.1); border: 1px solid rgba(167, 139, 250, 0.3); color: #ddd6fe; }
            .oz-note-box.warn { background: rgba(251, 146, 60, 0.08); border: 1px solid rgba(251, 146, 60, 0.3); color: #fed7aa; }
            .oz-route { font-family: var(--mono); font-size: 11px; color: var(--muted); word-break: break-word; }
            .oz-table { width: 100%; border-collapse: collapse; font-size: 12px; }
            .oz-table td { padding: 3px 4px; border-top: 1px solid var(--line); vertical-align: top; }
            .oz-table td:first-child { font-family: var(--mono); }
            .oz-table tr { cursor: pointer; }
            .oz-table tr:hover td { background: rgba(255, 255, 255, 0.03); }
            .oz-also { margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--line); color: var(--muted); font-size: 11px; }

            /* ---------- activity ---------- */
            .oz-feed-item { display: flex; gap: 8px; padding: 6px 4px; border-bottom: 1px solid var(--line); cursor: pointer; line-height: 1.35; }
            .oz-feed-item:hover { background: rgba(255, 255, 255, 0.03); }
            .oz-feed-item time { font-family: var(--mono); font-size: 11px; color: var(--faint); flex-shrink: 0; padding-top: 1px; }
            .oz-feed-item .oz-icon { flex-shrink: 0; width: 16px; text-align: center; }
            .oz-feed-item b { font-family: var(--mono); font-weight: 600; }

            /* ---------- layers ---------- */
            .oz-layers { border-top: 1px solid var(--line); padding: 8px 10px; display: flex; flex-wrap: wrap; gap: 4px 12px; }
            .oz-layers label { display: flex; align-items: center; gap: 5px; color: var(--muted); font-size: 11px; cursor: pointer; }
            .oz-legend { border-top: 1px solid var(--line); padding: 8px 10px; display: flex; flex-wrap: wrap; gap: 4px 12px; color: var(--faint); font-size: 10px; }
            .oz-legend span { display: inline-flex; align-items: center; gap: 5px; }
            .oz-legend .l-req { width: 14px; border-top: 2px dashed var(--warn); }
            .oz-legend .l-sel { width: 14px; border-top: 2px solid #fff; }
            .oz-legend .l-ho { width: 9px; height: 9px; border-radius: 50%; border: 2px solid var(--warn); }
            .oz-legend .l-tri { width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 10px solid var(--c); }

            .oz-filters { border-top: 1px solid var(--line); padding: 8px 10px 2px; }
            .oz-filter-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
            .oz-filter-row > span { width: 46px; font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
            .oz-filter-row > div { display: flex; gap: 4px; flex-wrap: wrap; }
            .oz-toggle {
                padding: 2px 8px; border-radius: 999px; font-size: 11px; font-family: var(--mono); cursor: pointer;
                border: 1px solid rgba(255, 255, 255, 0.14); background: transparent; color: var(--faint);
            }
            .oz-toggle.on { background: rgba(133, 133, 133, 0.28); border-color: rgba(200, 200, 200, 0.5); color: var(--text); }
            .oz-filter-row[data-offline] .oz-toggle.on { background: rgba(71, 85, 105, 0.35); }

            /* Aerodrome counts: activated FDRs not yet departed, and completed ones. */
            .oz-ad {
                display: flex; align-items: center; gap: 5px; padding: 2px 6px; border-radius: 4px; cursor: pointer;
                background: rgba(15, 23, 42, 0.92); border: 1px solid rgba(255, 255, 255, 0.12);
                font-family: var(--mono); font-size: 11px; line-height: 1.2; white-space: nowrap;
            }
            .oz-ad b { color: #f1f5f9; }
            .oz-ad .up { color: #4ade80; }
            .oz-ad .down { color: #f87171; }
            .oz-ad.selected { border-color: #fff; }
            .oz-ad-popup { font-size: 12px; min-width: 240px; }
            .oz-ad-popup h3 { margin: 0 0 6px; font-size: 14px; font-family: var(--mono); }
            .oz-ad-popup h4 { margin: 8px 0 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
            .oz-ad-popup table { width: 100%; border-collapse: collapse; }
            .oz-ad-popup td { padding: 2px 4px; border-top: 1px solid var(--line); font-family: var(--mono); font-size: 11px; white-space: nowrap; }
            .oz-ad-popup td:first-child { color: #f1f5f9; font-weight: 600; }
            .oz-ad-popup-wrap .mapboxgl-popup-content { max-height: 50vh; overflow-y: auto; }
            .oz-ad-popup tr { cursor: pointer; }
            .oz-ad-popup tr:hover td { background: rgba(255, 255, 255, 0.05); }

            /* ---------- markers ---------- */
            /* No position here: Mapbox positions markers absolutely, which also anchors the ::before/::after rings. */
            .oz-ac { cursor: pointer; transition: opacity 0.2s; }
            .oz-ac svg { display: block; filter: drop-shadow(0 0 2px rgba(0, 0, 0, 0.8)); }
            .oz-ac .oz-ac-label {
                position: absolute; left: 14px; top: -4px; font-family: var(--mono); font-size: 10px; white-space: nowrap;
                color: var(--c, #cbd5e1); text-shadow: 0 0 3px #000, 0 0 3px #000; pointer-events: none; display: none;
            }
            body.show-ac-labels .oz-ac .oz-ac-label { display: block; }
            .oz-ac.dim { opacity: 0.22; }
            .oz-ac.handover::after {
                content: ""; position: absolute; inset: -5px; border-radius: 50%; border: 2px solid var(--warn);
                animation: oz-pulse 1.2s ease-out infinite;
            }
            .oz-ac.selected::before {
                content: ""; position: absolute; inset: -7px; border-radius: 50%; border: 2px solid #fff;
            }
            @keyframes oz-pulse { from { opacity: 1; transform: scale(0.8); } to { opacity: 0; transform: scale(1.6); } }

            .oz-atis-marker {
                width: 22px; height: 22px; border-radius: 50%; background: #1e293b; border: 2px solid #a78bfa; color: #c4b5fd;
                display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; cursor: pointer;
            }
            .oz-note-marker {
                max-width: 180px; padding: 4px 7px; border-radius: 6px; font-size: 11px; line-height: 1.3; cursor: pointer;
                background: rgba(15, 23, 42, 0.92); border-left: 3px solid var(--c, #f472b6); color: var(--text);
                white-space: pre-wrap; word-break: break-word;
            }
            .oz-note-marker small { display: block; color: var(--faint); font-family: var(--mono); font-size: 9px; }

            .mapboxgl-popup-content { background: #0f172a; color: var(--text); border: 1px solid var(--line); border-radius: 8px; }
            .mapboxgl-popup-tip { display: none; }
            .mapboxgl-ctrl-bottom-left { left: 364px; }

            @media (max-width: 900px) {
                .oz-sidebar { width: calc(100% - 24px); top: auto; height: 42%; }
                .oz-detail { width: calc(100% - 24px); top: 72px; max-height: 45%; }
                .oz-stats .oz-stat:nth-child(n+3) { display: none; }
                .mapboxgl-ctrl-bottom-left { left: 0; }
            }
        </style>
    </head>
    <body>
        <div id="map"></div>

        <header class="oz-panel oz-topbar">
            <div class="oz-brand">Oz<span>Server</span> Ops</div>
            <select id="oz-server-select" aria-label="Server"></select>
            <div class="oz-stats" id="oz-stats"></div>
            <button type="button" class="oz-btn" id="oz-routes-btn" title="Draw every flight's route as vatSys parsed it">Show all routes</button>
            <div class="oz-live" id="oz-live" title="Live updates"><i></i><span>Connecting…</span></div>
        </header>

        <aside class="oz-panel oz-sidebar">
            <nav class="oz-tabs" id="oz-tabs">
                <button data-tab="controllers" class="active">ATC</button>
                <button data-tab="sectors">Sectors</button>
                <button data-tab="requests">Requests<span class="oz-badge warn" id="oz-req-badge" hidden></span></button>
                <button data-tab="flights">Flights</button>
                <button data-tab="activity">Activity<span class="oz-badge accent" id="oz-feed-badge" hidden></span></button>
            </nav>
            <div class="oz-tab-body" data-body="controllers" id="oz-controllers"></div>
            <div class="oz-tab-body" data-body="sectors" hidden>
                <input class="oz-search" id="oz-sector-search" placeholder="Filter sectors (name, callsign, owner)…">
                <div id="oz-sectors"></div>
            </div>
            <div class="oz-tab-body" data-body="requests" id="oz-requests" hidden></div>
            <div class="oz-tab-body" data-body="flights" hidden>
                <input class="oz-search" id="oz-flight-search" placeholder="Filter flights (callsign, type, airport, controller)…">
                <div id="oz-flights"></div>
            </div>
            <div class="oz-tab-body" data-body="activity" id="oz-activity" hidden></div>

            <div class="oz-filters" id="oz-filters">
                <div class="oz-filter-row"><span>Online</span><div data-filter-group="online"></div></div>
                <div class="oz-filter-row"><span>Offline</span><div data-filter-group="offline"></div></div>
            </div>
            <div class="oz-layers" id="oz-layers">
                <label><input type="checkbox" data-layer="labels"> Sector labels</label>
                <label><input type="checkbox" data-layer="aircraft"> Aircraft</label>
                <label><input type="checkbox" data-layer="acLabels"> Callsigns</label>
                <label><input type="checkbox" data-layer="aerodromes"> Departures / completed</label>
                <label><input type="checkbox" data-layer="requests"> Requests</label>
                <label><input type="checkbox" data-layer="annotations"> Notes &amp; drawings</label>
                <label><input type="checkbox" data-layer="atis"> ATIS</label>
            </div>
            <div class="oz-legend">
                <span><i class="oz-swatch" style="--c:rgb(133,133,133)"></i>Online</span>
                <span><i class="oz-swatch" style="--c:#22d3ee"></i>Highlighted</span>
                <span><i class="l-sel"></i>Selected</span>
                <span><i class="l-req"></i>Requested</span>
                <span><i class="l-tri" style="--c:#16a34a"></i>Owned tag</span>
                <span><i class="l-tri" style="--c:#94a3b8"></i>No owner</span>
                <span><b style="color:#4ade80">▲</b>Awaiting departure</span>
                <span><b style="color:#f87171">▼</b>Completed</span>
                <span><i class="l-ho"></i>Handoff</span>
            </div>
        </aside>

        <section class="oz-panel oz-detail" id="oz-detail" hidden></section>

        <script>
            // ------------------------------------------------------------------------------------
            // OzServer Ops - a read-only picture of what the vatSys plugin is coordinating: who
            // holds which sectors, the requests between controllers, which controller each flight
            // is with, and the shared notes/drawings. Nothing here can change anything; all of it
            // is acted on from vatSys.
            //
            // Data: /map/sector-shapes once (geometry), then /map/ops (ownership, controllers,
            // requests, annotations), /map/aircraft, /map/atis and /map/controllers - re-read on
            // the API's SSE signals, with a slow poll underneath in case the stream is unavailable.
            // ------------------------------------------------------------------------------------
            const apiBaseUrl = @json(rtrim(config('services.ozserver_api.url'), '/'));
            const POLL_INTERVAL_MS = 15000;
            const FEED_LIMIT = 150;

            const DEFAULT_SERVERS = [
                { value: 'live', label: 'Live VATSIM' },
                { value: 'sb1', label: 'SweatBox 1' },
                { value: 'sb2', label: 'SweatBox 2' },
            ];
            let currentServer = new URLSearchParams(window.location.search).get('server');

            const TYPE_RANK = { FMP: 0, CTR: 1, FSS: 1, APP: 2, DEP: 2, TWR: 3, GND: 4, DEL: 4 };
            const UNSTAFFED_COLOUR = '#475569';
            const INFILL_COLOUR = 'rgb(133, 133, 133)'; // vatSys's own "Infill" (Colours.xml) - an online sector
            const HIGHLIGHT_COLOUR = '#22d3ee';
            const REQUEST_COLOUR = '#fb923c';
            const TRACKED_COLOUR = '#16a34a';   // a tag someone owns
            const UNTRACKED_COLOUR = '#94a3b8'; // a tag coasting with no owner (kept current by its data authority)
            const RESPONSIBLE_COLOUR = '#ef4444'; // line to the controller keeping an untracked FDR current

            // The map's position filters: which kinds of sector are drawn, separately for online
            // (someone holds it) and offline. Categories follow the controller positions, not the
            // dataset's raw types.
            const CATEGORY_OF_TYPE = { DEL: 'ADC', GND: 'ADC', TWR: 'ADC', APP: 'APP', DEP: 'DEP', CTR: 'ENR', FSS: 'ENR', FMP: 'FMP' };
            const CATEGORIES = [
                { id: 'ADC', label: 'ADC', title: 'Aerodrome: DEL, GND, TWR' },
                { id: 'APP', label: 'APP', title: 'Approach' },
                { id: 'DEP', label: 'DEP', title: 'Departures' },
                { id: 'ENR', label: 'ENR', title: 'En route: CTR, FSS' },
                { id: 'FMP', label: 'FMP', title: 'Flow' },
            ];
            const categoryOf = (type) => CATEGORY_OF_TYPE[type] ?? 'ENR';

            // Saved in a cookie so a returning visitor gets the same map back.
            const PREFS_COOKIE = 'oz_ops_prefs';
            const DEFAULT_PREFS = {
                layers: { labels: true, aircraft: true, aerodromes: true, acLabels: false, requests: true, annotations: true, atis: true, routes: false },
                online: { ADC: true, APP: true, DEP: true, ENR: true, FMP: true },
                offline: { ADC: false, APP: false, DEP: false, ENR: true, FMP: false },
            };
            function loadPrefs() {
                try {
                    const raw = document.cookie.split('; ').find((c) => c.startsWith(PREFS_COOKIE + '='));
                    const saved = raw ? JSON.parse(decodeURIComponent(raw.slice(PREFS_COOKIE.length + 1))) : {};
                    return {
                        layers: { ...DEFAULT_PREFS.layers, ...(saved.layers ?? {}) },
                        online: { ...DEFAULT_PREFS.online, ...(saved.online ?? {}) },
                        offline: { ...DEFAULT_PREFS.offline, ...(saved.offline ?? {}) },
                    };
                } catch {
                    return structuredClone(DEFAULT_PREFS);
                }
            }
            function savePrefs() {
                const value = encodeURIComponent(JSON.stringify({ layers: state.layers, online: state.online, offline: state.offline }));
                document.cookie = `${PREFS_COOKIE}=${value}; max-age=${60 * 60 * 24 * 365}; path=/; SameSite=Lax`;
            }
            const prefs = loadPrefs();

            const state = {
                shapes: new Map(),          // sector name -> { name, full_name, type, callsign, frequency, responsible_sectors, bbox, drawn, rank }
                coveredBy: new Map(),       // sector name -> [parent sector names whose responsible_sectors include it]
                ops: null,                  // last /map/ops snapshot
                owners: new Map(),          // sector name -> ownership row
                controllers: new Map(),     // callsign -> controller (from /map/ops)
                flights: [],
                flightsByCallsign: new Map(),
                atis: [],
                vatsimControllers: [],
                selection: null,            // { kind: 'controller'|'sector'|'flight'|'request', id }
                layers: prefs.layers,
                online: prefs.online,
                offline: prefs.offline,
                feed: [],
                unseenFeed: 0,
                activeTab: 'controllers',
                lastUpdate: null,
                streamOpen: false,
            };

            // ---------------- helpers ----------------

            // Everything shown here was typed into a vatSys client somewhere, so none of it goes
            // into markup unescaped.
            function esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            }

            // A stable colour per controller callsign, so the same person reads as the same colour
            // across the map, the lists and the activity feed. Golden-angle hue spacing keeps
            // neighbouring callsigns apart.
            const colourCache = new Map();
            function colourFor(callsign) {
                if (!callsign) return UNSTAFFED_COLOUR;
                if (colourCache.has(callsign)) return colourCache.get(callsign);
                let hash = 0;
                for (const ch of callsign.toUpperCase()) hash = (hash * 31 + ch.charCodeAt(0)) >>> 0;
                const hue = Math.round((hash * 137.508) % 360);
                const colour = `hsl(${hue}, 78%, 60%)`;
                colourCache.set(callsign, colour);
                return colour;
            }

            function isFlow(callsign) { return !!callsign && /_FMP$/i.test(callsign); }
            function isHandover(stateValue) { return stateValue === 'STATE_HANDOVER' || stateValue === 'STATE_HANDOVER_FIRST'; }

            function zulu(date) { return new Date(date).toISOString().slice(11, 19) + 'z'; }
            function hhmm(iso) { return iso ? new Date(iso).toISOString().slice(11, 16) + 'z' : null; }
            function ago(iso) {
                if (!iso) return '';
                const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
                if (s < 60) return `${s}s`;
                if (s < 3600) return `${Math.floor(s / 60)}m`;
                return `${Math.floor(s / 3600)}h${String(Math.floor((s % 3600) / 60)).padStart(2, '0')}`;
            }
            function freq(value) {
                if (value === null || value === undefined || value === '') return null;
                const n = Number(value);
                if (!Number.isFinite(n)) return String(value);
                // Seen as MHz (124.4), kHz (124400) or Hz (124400000) depending on the source.
                return (n >= 1e6 ? n / 1e6 : n >= 1e4 ? n / 1e3 : n).toFixed(3);
            }

            function controllerChip(callsign, extraClass = '') {
                if (!callsign) return '<span class="oz-chip plain">no owner</span>';
                return `<button class="oz-chip ${extraClass}" style="--c:${colourFor(callsign)}" data-select="controller:${esc(callsign)}">${esc(callsign)}</button>`;
            }
            function sectorChip(name, extraClass = '') {
                const owner = state.owners.get(name);
                const colour = owner ? colourFor(owner.callsign) : UNSTAFFED_COLOUR;
                return `<button class="oz-chip ${extraClass}" style="--c:${colour}" data-select="sector:${esc(name)}">${esc(name)}</button>`;
            }
            function flightChip(callsign) {
                const flight = state.flightsByCallsign.get(callsign);
                return `<button class="oz-chip" style="--c:${colourFor(flight?.controlling_callsign)}" data-select="flight:${esc(callsign)}">${esc(callsign)}</button>`;
            }
            function sectorList(names, currentName = null, limit = 60) {
                const sorted = [...names].sort();
                const shown = sorted.slice(0, limit).map((n) => sectorChip(n, n === currentName ? 'current' : '')).join('');
                return shown + (sorted.length > limit ? ` <span class="oz-badge">+${sorted.length - limit}</span>` : '');
            }

            function apiUrl(path) {
                const url = new URL(apiBaseUrl + path);
                if (currentServer) url.searchParams.set('server', currentServer);
                return url.toString();
            }
            async function getJson(path, scoped = true) {
                const response = await fetch(scoped ? apiUrl(path) : apiBaseUrl + path, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`${path}: HTTP ${response.status}`);
                return response.json();
            }

            // ---------------- map ----------------

            mapboxgl.accessToken = document.querySelector('meta[name="mapbox-token"]').content;
            const map = new mapboxgl.Map({
                container: 'map',
                style: 'mapbox://styles/mapbox/dark-v11',
                projection: 'mercator',
                center: [134, -27],
                zoom: 3.6,
                attributionControl: false,
            });
            // North-up only: aircraft icons are rotated by heading in CSS rather than by the marker,
            // so their callsign labels stay level.
            map.dragRotate.disable();
            map.touchZoomRotate.disableRotation();
            map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'bottom-right');
            map.addControl(new mapboxgl.AttributionControl({ compact: true }), 'bottom-left');
            // Layer/style validation problems are reported here rather than thrown.
            map.on('error', (event) => console.warn('map', event.error?.message ?? event.error));

            const aircraftMarkers = new Map(); // callsign -> { marker, el }
            const atisMarkers = new Map();     // icao -> marker
            const noteMarkers = new Map();     // annotation id -> marker

            // Oceanic sectors crossing the antimeridian would otherwise draw straight across the map.
            function unwrapRing(ring) {
                const out = [ring[0]];
                for (let i = 1; i < ring.length; i++) {
                    const prevLon = out[i - 1][0];
                    let [lon, lat] = ring[i];
                    while (lon - prevLon > 180) lon -= 360;
                    while (lon - prevLon < -180) lon += 360;
                    out.push([lon, lat]);
                }
                return out;
            }

            function loadShapes(shapes) {
                state.shapes.clear();
                state.coveredBy.clear();
                const features = [];

                for (const shape of shapes) {
                    const rings = (shape.boundary ?? [])
                        .filter((ring) => ring.length > 2)
                        .map((ring) => unwrapRing(ring.map((p) => [p.lon, p.lat])));
                    let bbox = null;
                    for (const ring of rings) {
                        for (const [lon, lat] of ring) {
                            if (!bbox) bbox = [lon, lat, lon, lat];
                            bbox[0] = Math.min(bbox[0], lon); bbox[1] = Math.min(bbox[1], lat);
                            bbox[2] = Math.max(bbox[2], lon); bbox[3] = Math.max(bbox[3], lat);
                        }
                    }
                    const rank = TYPE_RANK[shape.type] ?? 2;
                    state.shapes.set(shape.name, { ...shape, bbox, drawn: rings.length > 0, rank,
                        area: bbox ? (bbox[2] - bbox[0]) * (bbox[3] - bbox[1]) : Infinity });
                    for (const child of shape.responsible_sectors ?? []) {
                        if (!state.coveredBy.has(child)) state.coveredBy.set(child, []);
                        state.coveredBy.get(child).push(shape.name);
                    }
                    if (rings.length) {
                        features.push({
                            type: 'Feature',
                            properties: { name: shape.name, type: shape.type, rank },
                            geometry: { type: 'MultiPolygon', coordinates: rings.map((ring) => [ring]) },
                        });
                    }
                }

                // Big areas first, so smaller sectors (approach, tower) draw on top of the
                // en-route airspace they sit inside and stay visible and clickable.
                features.sort((a, b) => a.properties.rank - b.properties.rank
                    || state.shapes.get(b.properties.name).area - state.shapes.get(a.properties.name).area);

                const data = { type: 'FeatureCollection', features };
                if (map.getSource('sectors')) {
                    map.getSource('sectors').setData(data);
                    lastSectorStates.clear();
                    return;
                }
                map.addSource('sectors', { type: 'geojson', data, promoteId: 'name' });

                // Every sector's look is driven by feature-state set in applySectorStates: owned,
                // show (passes the position filters), hl/dim/sel (selection) and req (requested).
                // A sector the filters hide draws nothing at all, whatever else is true of it.
                const fs = (key) => ['boolean', ['feature-state', key], false];
                const hidden = ['!', fs('show')];
                map.addLayer({
                    id: 'sectors-fill', type: 'fill', source: 'sectors',
                    paint: {
                        'fill-color': ['coalesce', ['feature-state', 'colour'], UNSTAFFED_COLOUR],
                        'fill-opacity': ['case',
                            hidden, 0,
                            fs('sel'), 0.5,
                            fs('hl'), 0.4,
                            fs('dim'), ['case', fs('owned'), 0.12, 0],
                            fs('owned'), 0.35,
                            0],
                    },
                });
                map.addLayer({
                    id: 'sectors-line', type: 'line', source: 'sectors',
                    paint: {
                        'line-color': ['case', fs('hl'), ['coalesce', ['feature-state', 'colour'], HIGHLIGHT_COLOUR],
                            fs('owned'), '#a3a3a3', '#334155'],
                        'line-width': ['case', fs('hl'), 2.2, fs('owned'), 1.1, 0.6],
                        'line-opacity': ['case',
                            hidden, 0,
                            fs('hl'), 1,
                            fs('dim'), ['case', fs('owned'), 0.35, 0.25],
                            fs('owned'), 0.85,
                            0.5],
                    },
                });
                map.addLayer({
                    id: 'sectors-requested', type: 'line', source: 'sectors',
                    paint: {
                        'line-color': REQUEST_COLOUR, 'line-width': 2.5, 'line-dasharray': [1.5, 1.2],
                        'line-opacity': ['case', fs('req'), 1, 0],
                    },
                });
                map.addLayer({
                    id: 'sectors-selected', type: 'line', source: 'sectors',
                    paint: { 'line-color': '#ffffff', 'line-width': 3, 'line-opacity': ['case', fs('sel'), 1, 0] },
                });
                map.addLayer({
                    id: 'sectors-label', type: 'symbol', source: 'sectors',
                    layout: {
                        'text-field': ['get', 'name'], 'text-size': 11, 'text-font': ['DIN Pro Medium', 'Arial Unicode MS Regular'],
                        'symbol-sort-key': ['-', 10, ['get', 'rank']],
                    },
                    paint: {
                        'text-color': ['case', fs('hl'), ['coalesce', ['feature-state', 'colour'], HIGHLIGHT_COLOUR], fs('owned'), '#e5e5e5', '#94a3b8'],
                        'text-halo-color': '#020617', 'text-halo-width': 1.2,
                        'text-opacity': ['case', hidden, 0, fs('sel'), 1, fs('hl'), 1, fs('dim'), 0, fs('owned'), 0.9, 0],
                    },
                });

                // Routes: what is still to be flown as a solid line, what has been flown (selected
                // flight only) dashed, and the selected flight's waypoints named.
                const empty = { type: 'FeatureCollection', features: [] };
                map.addSource('routes', { type: 'geojson', data: empty });
                map.addSource('route-points', { type: 'geojson', data: empty });
                map.addLayer({
                    id: 'routes-flown', type: 'line', source: 'routes', filter: ['==', ['get', 'kind'], 'flown'],
                    layout: { 'line-join': 'round' },
                    paint: { 'line-color': ['get', 'colour'], 'line-width': 1.5, 'line-opacity': 0.5, 'line-dasharray': [2, 2] },
                });
                map.addLayer({
                    id: 'routes-remaining', type: 'line', source: 'routes', filter: ['==', ['get', 'kind'], 'remaining'],
                    layout: { 'line-join': 'round', 'line-cap': 'round', 'line-sort-key': ['case', ['get', 'selected'], 1, 0] },
                    paint: {
                        'line-color': ['get', 'colour'],
                        'line-width': ['case', ['get', 'selected'], 2.5, 1.2],
                        'line-opacity': ['case', ['get', 'selected'], 0.95, 0.5],
                    },
                });
                map.addLayer({
                    id: 'route-points', type: 'circle', source: 'route-points',
                    paint: { 'circle-radius': 3, 'circle-color': '#020617', 'circle-stroke-width': 1.5, 'circle-stroke-color': ['get', 'colour'] },
                });
                map.addLayer({
                    id: 'route-point-labels', type: 'symbol', source: 'route-points',
                    layout: {
                        'text-field': ['get', 'name'], 'text-size': 10, 'text-offset': [0, 0.9], 'text-anchor': 'top',
                        'text-font': ['DIN Pro Medium', 'Arial Unicode MS Regular'],
                    },
                    paint: { 'text-color': '#cbd5e1', 'text-halo-color': '#020617', 'text-halo-width': 1.2,
                        'text-opacity': ['case', ['get', 'passed'], 0.45, 1] },
                });

                // Who keeps the selected untracked FDR current: a red line from the aircraft to the
                // nearest point of that controller's sector, labelled.
                map.addSource('responsible', { type: 'geojson', data: empty });
                map.addLayer({
                    id: 'responsible-line', type: 'line', source: 'responsible', filter: ['==', ['geometry-type'], 'LineString'],
                    layout: { 'line-cap': 'round' },
                    paint: { 'line-color': RESPONSIBLE_COLOUR, 'line-width': 2.5, 'line-opacity': 0.95 },
                });
                map.addLayer({
                    id: 'responsible-end', type: 'circle', source: 'responsible',
                    filter: ['all', ['==', ['geometry-type'], 'Point'], ['!', ['has', 'label']]],
                    paint: { 'circle-radius': 4, 'circle-color': RESPONSIBLE_COLOUR, 'circle-stroke-color': '#020617', 'circle-stroke-width': 1.5 },
                });
                map.addLayer({
                    id: 'responsible-label', type: 'symbol', source: 'responsible',
                    filter: ['all', ['==', ['geometry-type'], 'Point'], ['has', 'label']],
                    layout: {
                        'text-field': ['get', 'label'], 'text-size': 11, 'text-anchor': 'left', 'text-offset': [0.8, 0],
                        'text-font': ['DIN Pro Medium', 'Arial Unicode MS Regular'], 'text-allow-overlap': true, 'text-ignore-placement': true,
                    },
                    paint: { 'text-color': '#fecaca', 'text-halo-color': '#020617', 'text-halo-width': 1.4 },
                });

                map.addSource('annotation-strokes', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
                map.addLayer({
                    id: 'annotation-strokes', type: 'line', source: 'annotation-strokes',
                    layout: { 'line-cap': 'round', 'line-join': 'round' },
                    paint: { 'line-color': ['get', 'colour'], 'line-width': 2.5, 'line-opacity': 0.9 },
                });

                map.on('click', onMapClick);
                map.on('mousemove', 'sectors-fill', () => { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', 'sectors-fill', () => { map.getCanvas().style.cursor = ''; });
            }

            // Whether the position filters draw this sector. Anything the current selection lights
            // up is always drawn - choosing a controller should show all of their airspace even if
            // its kind is filtered out.
            function sectorShown(name, focus = selectionFocus()) {
                if (focus?.sectors.has(name) || focus?.selected === name) return true;
                const shape = state.shapes.get(name);
                const filters = state.owners.has(name) ? state.online : state.offline;
                return !!filters[categoryOf(shape?.type)];
            }

            // Overlapping airspace (an approach sector inside the en-route sector above it) means a
            // click often lands on several sectors. Prefer one someone holds, then the most specific
            // (tower over approach over en-route), then the smallest - and offer the rest in the panel.
            function onMapClick(e) {
                // A click on a marker (an ATIS letter, an aerodrome count) or inside a popup is
                // about that, not the airspace beneath it.
                if (e.originalEvent?.target?.closest?.('.mapboxgl-marker, .mapboxgl-popup')) return;
                const hits = map.queryRenderedFeatures(e.point, { layers: ['sectors-fill'] });
                const focus = selectionFocus();
                const names = [...new Set(hits.map((f) => f.properties.name))]
                    .filter((n) => state.shapes.has(n) && sectorShown(n, focus));
                if (!names.length) { select(null); return; }
                names.sort((a, b) => {
                    const sa = state.shapes.get(a), sb = state.shapes.get(b);
                    return (state.owners.has(b) - state.owners.has(a)) || (sb.rank - sa.rank) || (sa.area - sb.area);
                });
                clickStack = names;
                select({ kind: 'sector', id: names[0] }, { fly: false });
            }
            let clickStack = [];

            // ---------------- selection ----------------

            function select(selection, { fly = true, fromHash = false } = {}) {
                state.selection = selection;
                // The "also under the click" list only means something while the selection is one
                // of the sectors from that click.
                if (selection?.kind !== 'sector' || !clickStack.includes(selection.id)) clickStack = [];
                if (!fromHash) writeHash();
                if (selection?.kind === 'flight' && !routes.has(selection.id)) refreshRoutes();
                renderAll();
                if (fly && selection) flyToSelection();
            }

            // What the current selection lights up on the map: the set of sectors, the controller
            // whose aircraft stay bright, and an override colour (requests are drawn in orange).
            function selectionFocus() {
                const sel = state.selection;
                if (!sel) return null;

                if (sel.kind === 'controller') {
                    const c = state.controllers.get(sel.id);
                    return { sectors: new Set(c?.sectors ?? []), controller: sel.id };
                }
                if (sel.kind === 'sector') {
                    const owner = state.owners.get(sel.id);
                    if (owner) {
                        const c = state.controllers.get(owner.callsign);
                        return { sectors: new Set(c?.sectors ?? [sel.id]), controller: owner.callsign, selected: sel.id };
                    }
                    const shape = state.shapes.get(sel.id);
                    return { sectors: new Set([sel.id, ...(shape?.responsible_sectors ?? [])]), controller: null, selected: sel.id };
                }
                // A flight is shown on its own: selecting one marks the aircraft and opens its panel,
                // but leaves the airspace picture alone - no controller's sectors light up or dim.
                if (sel.kind === 'flight') {
                    return { sectors: new Set(), controller: null, selected: null, flight: sel.id, noDim: true };
                }
                if (sel.kind === 'request') {
                    const rows = (state.ops?.requests ?? []).filter((r) => r.group_id === sel.id);
                    return { sectors: new Set(rows.map((r) => r.sector)), controller: rows[0]?.requesting_callsign ?? null,
                        colour: REQUEST_COLOUR, request: sel.id };
                }
                return null;
            }

            function flyToSelection() {
                const focus = selectionFocus();
                if (!focus) return;
                if (state.selection.kind === 'flight') {
                    const f = state.flightsByCallsign.get(state.selection.id);
                    const point = f ? flightPoint(f) : null;
                    if (point) map.easeTo({ center: point, zoom: Math.max(map.getZoom(), 6), duration: 700 });
                    return;
                }
                const names = state.selection.kind === 'sector' && !state.owners.has(state.selection.id)
                    ? [state.selection.id] : [...focus.sectors];
                let bbox = null;
                for (const name of names) {
                    const b = state.shapes.get(name)?.bbox;
                    if (!b) continue;
                    bbox = bbox ? [Math.min(bbox[0], b[0]), Math.min(bbox[1], b[1]), Math.max(bbox[2], b[2]), Math.max(bbox[3], b[3])] : [...b];
                }
                if (!bbox) return;
                const wide = window.innerWidth > 900;
                map.fitBounds([[bbox[0], bbox[1]], [bbox[2], bbox[3]]], {
                    padding: wide ? { top: 90, bottom: 40, left: 380, right: 400 } : 40,
                    maxZoom: 8, duration: 700,
                });
            }

            function writeHash() {
                const sel = state.selection;
                const hash = sel ? `#${sel.kind}=${encodeURIComponent(sel.id)}` : '';
                history.replaceState(null, '', `/ops?server=${encodeURIComponent(currentServer)}${hash}`);
            }
            function readHash() {
                const match = /^#(controller|sector|flight|request)=(.+)$/.exec(window.location.hash);
                return match ? { kind: match[1], id: decodeURIComponent(match[2]) } : null;
            }

            // ---------------- rendering: map ----------------

            const lastSectorStates = new Map();
            function applySectorStates() {
                if (!map.getSource('sectors')) return;
                const focus = selectionFocus();
                const requested = new Set(state.layers.requests ? (state.ops?.requests ?? []).filter((r) => !r.rejected_at).map((r) => r.sector) : []);

                for (const [name, shape] of state.shapes) {
                    if (!shape.drawn) continue;
                    const owner = state.owners.get(name);
                    const hl = !!focus && focus.sectors.has(name);
                    const next = {
                        owned: !!owner,
                        show: sectorShown(name, focus),
                        colour: hl ? (focus.colour ?? HIGHLIGHT_COLOUR) : (owner ? INFILL_COLOUR : UNSTAFFED_COLOUR),
                        hl,
                        dim: !!focus && !focus.noDim && !hl,
                        sel: !!focus && focus.selected === name,
                        req: requested.has(name),
                    };
                    const key = JSON.stringify(next);
                    if (lastSectorStates.get(name) === key) continue;
                    lastSectorStates.set(name, key);
                    map.setFeatureState({ source: 'sectors', id: name }, next);
                }
            }

            function aircraftSvg(colour) {
                return `<svg width="16" height="16" viewBox="0 0 16 16"><polygon points="8,0 14,16 8,12 2,16" fill="${colour}" stroke="#020617" stroke-width="0.8"/></svg>`;
            }

            // Where an FDR belongs on the map:
            //  - completed (STATE_FINISHED): counted at its destination aerodrome (red ▼);
            //  - activated but not departed: counted at its departure aerodrome (green ▲) - no ATD
            //    yet and still on the ground (or with no evidence it is airborne);
            //  - otherwise airborne: an aircraft tag at its position, dark green if someone owns
            //    the tag, grey if it is coasting with no owner.
            function flightCategory(f) {
                if (f.state === 'STATE_FINISHED') return 'completed';
                if (!f.atd && f.dep_airport) {
                    if (f.on_ground === true) return 'pending';
                    if (f.on_ground == null && !((f.altitude ?? 0) > 1500) && !((f.ground_speed ?? 0) > 60)) return 'pending';
                    if (!Number.isFinite(f.lat)) return 'pending';
                }
                return 'airborne';
            }
            function aerodromePoint(f, which) {
                const lat = which === 'dep' ? f.dep_lat : f.des_lat;
                const lon = which === 'dep' ? f.dep_lon : f.des_lon;
                if (Number.isFinite(lat) && Number.isFinite(lon)) return [lon, lat];
                return Number.isFinite(f.lat) && Number.isFinite(f.lon) ? [f.lon, f.lat] : null;
            }
            // Where the map shows this flight - for flying to it from a list.
            function flightPoint(f) {
                const category = flightCategory(f);
                if (category === 'pending') return aerodromePoint(f, 'dep');
                if (category === 'completed') return aerodromePoint(f, 'des');
                return Number.isFinite(f.lat) && Number.isFinite(f.lon) ? [f.lon, f.lat] : null;
            }

            function flightColour(f) {
                const category = flightCategory(f);
                if (category === 'pending') return '#4ade80';
                if (category === 'completed') return '#f87171';
                return f.controlling_callsign ? TRACKED_COLOUR : UNTRACKED_COLOUR;
            }
            function categoryBadge(f) {
                const category = flightCategory(f);
                if (category === 'pending') return '<span class="oz-badge good">▲ awaiting dep</span>';
                if (category === 'completed') return '<span class="oz-badge bad">▼ completed</span>';
                return '';
            }

            // marker.addTo() first removes the marker - closing any popup attached to it - so calling
            // it on every refresh (as this page used to) shut a popup moments after it opened. Only
            // add or remove when visibility actually changes.
            const markersOnMap = new WeakSet();
            function setMarkerVisible(marker, visible) {
                if (visible && !markersOnMap.has(marker)) { marker.addTo(map); markersOnMap.add(marker); }
                else if (!visible && markersOnMap.has(marker)) { marker.remove(); markersOnMap.delete(marker); }
            }

            const aerodromeMarkers = new Map(); // ICAO -> { marker, el, popup, point, html }

            function aerodromePopupHtml(icao, pending, completed) {
                const row = (f, cells) => `<tr data-select="flight:${esc(f.callsign)}">${cells.map((c) => `<td>${esc(c ?? '')}</td>`).join('')}</tr>`;
                const squawk = (f) => f.assigned_ssr_code != null ? String(f.assigned_ssr_code).padStart(4, '0') : '';
                return `<div class="oz-ad-popup"><h3>${esc(icao)}</h3>
                    ${pending.length ? `<h4><span style="color:#4ade80">▲</span> Awaiting departure (${pending.length})</h4>
                        <table>${pending.map((f) => row(f, [f.callsign, f.aircraft_type, '→ ' + (f.des_airport ?? '????'),
                            [f.sid_star_string, f.runway_string ?? f.departure_runway].filter(Boolean).join(' '),
                            squawk(f), f.etd ? 'ETD ' + hhmm(f.etd) : '', f.controlling_callsign ?? 'no owner'])).join('')}</table>` : ''}
                    ${completed.length ? `<h4><span style="color:#f87171">▼</span> Completed (${completed.length})</h4>
                        <table>${completed.map((f) => row(f, [f.callsign, f.aircraft_type, 'from ' + (f.dep_airport ?? '????'),
                            f.atd ? 'ATD ' + hhmm(f.atd) : ''])).join('')}</table>` : ''}
                </div>`;
            }

            function renderAerodromes() {
                const focus = selectionFocus();
                const groups = new Map(); // ICAO -> { point, pending: [], completed: [] }
                for (const f of state.flights) {
                    const category = flightCategory(f);
                    if (category === 'airborne') continue;
                    const icao = category === 'pending' ? f.dep_airport : f.des_airport;
                    const point = aerodromePoint(f, category === 'pending' ? 'dep' : 'des');
                    if (!icao || !point) continue;
                    if (!groups.has(icao)) groups.set(icao, { point, pending: [], completed: [] });
                    groups.get(icao)[category].push(f);
                }

                for (const [icao, group] of groups) {
                    let entry = aerodromeMarkers.get(icao);
                    if (!entry) {
                        const el = document.createElement('div');
                        el.className = 'oz-ad';
                        el.title = 'Show flights';
                        // Opened by hand rather than through marker.setPopup: the popup has to stay
                        // open across refreshes (its rows are how you open each FDR), and a click on
                        // the marker must not also select whatever sector lies underneath it.
                        const popup = new mapboxgl.Popup({ offset: 12, maxWidth: '520px', className: 'oz-ad-popup-wrap' });
                        const marker = new mapboxgl.Marker({ element: el, anchor: 'left', offset: [8, 0] });
                        entry = { marker, el, popup, point: null, html: '' };
                        el.addEventListener('click', (event) => {
                            event.stopPropagation();
                            if (popup.isOpen()) { popup.remove(); return; }
                            popup.setLngLat(entry.point).setHTML(entry.html).addTo(map);
                        });
                        aerodromeMarkers.set(icao, entry);
                    }
                    const sortByCallsign = (a, b) => a.callsign.localeCompare(b.callsign);
                    group.pending.sort(sortByCallsign);
                    group.completed.sort(sortByCallsign);
                    entry.el.innerHTML = `<b>${esc(icao)}</b>`
                        + (group.pending.length ? `<span class="up">▲${group.pending.length}</span>` : '')
                        + (group.completed.length ? `<span class="down">▼${group.completed.length}</span>` : '');
                    entry.el.classList.toggle('selected', !!focus?.flight
                        && [...group.pending, ...group.completed].some((f) => f.callsign === focus.flight));
                    entry.point = group.point;
                    const html = aerodromePopupHtml(icao, group.pending, group.completed);
                    // Rewritten only when it changed, so an open list is not reset under the pointer.
                    if (html !== entry.html) {
                        entry.html = html;
                        if (entry.popup.isOpen()) entry.popup.setHTML(html);
                    }
                    if (entry.popup.isOpen()) entry.popup.setLngLat(group.point);
                    entry.marker.setLngLat(group.point);
                    setMarkerVisible(entry.marker, state.layers.aerodromes);
                    if (!state.layers.aerodromes) entry.popup.remove();
                }
                for (const [icao, entry] of aerodromeMarkers) {
                    if (!groups.has(icao)) { entry.popup.remove(); setMarkerVisible(entry.marker, false); aerodromeMarkers.delete(icao); }
                }
            }

            function renderAircraft() {
                renderAerodromes();
                const focus = selectionFocus();
                const seen = new Set();
                for (const flight of state.flights) {
                    if (flightCategory(flight) !== 'airborne') continue;
                    if (!Number.isFinite(flight.lat) || !Number.isFinite(flight.lon)) continue;
                    seen.add(flight.callsign);
                    let entry = aircraftMarkers.get(flight.callsign);
                    if (!entry) {
                        const el = document.createElement('div');
                        el.className = 'oz-ac';
                        el.addEventListener('click', (event) => {
                            event.stopPropagation();
                            select({ kind: 'flight', id: flight.callsign }, { fly: false });
                        });
                        const marker = new mapboxgl.Marker({ element: el });
                        entry = { marker, el, colour: null };
                        aircraftMarkers.set(flight.callsign, entry);
                    }
                    const colour = flight.controlling_callsign ? TRACKED_COLOUR : UNTRACKED_COLOUR;
                    if (entry.colour !== colour) {
                        entry.el.innerHTML = aircraftSvg(colour) + `<span class="oz-ac-label"></span>`;
                        entry.colour = colour;
                    }
                    const label = entry.el.querySelector('.oz-ac-label');
                    label.textContent = `${flight.callsign} ${flight.altitude != null ? Math.round(flight.altitude / 100).toString().padStart(3, '0') : ''}`;
                    label.style.setProperty('--c', colour);
                    entry.el.querySelector('svg').style.transform = `rotate(${flight.heading ?? 0}deg)`;

                    entry.el.title = `${flight.callsign}${flight.controlling_callsign ? ' - ' + flight.controlling_callsign : ' - no owner'}`;
                    entry.el.classList.toggle('handover', isHandover(flight.state));
                    entry.el.classList.toggle('selected', focus?.flight === flight.callsign);
                    entry.el.classList.toggle('dim', !!focus && !focus.flight && focus.controller !== flight.controlling_callsign
                        && !(focus.selected && flight.current_sector === focus.selected));
                    entry.marker.setLngLat([flight.lon, flight.lat]);
                    setMarkerVisible(entry.marker, state.layers.aircraft);
                }
                for (const [callsign, entry] of aircraftMarkers) {
                    if (!seen.has(callsign)) { setMarkerVisible(entry.marker, false); aircraftMarkers.delete(callsign); }
                }
            }

            // ---------------- routes ----------------

            const routes = new Map(); // callsign -> { points: [{n, lat, lon}], next }
            let routesLoaded = false;

            // Routes are only fetched while something needs them: all routes shown, or a flight
            // selected that has one.
            function routesWanted() {
                if (state.layers.routes) return true;
                const sel = state.selection;
                return sel?.kind === 'flight' && !!state.flightsByCallsign.get(sel.id)?.has_route;
            }

            async function refreshRoutes() {
                if (!routesWanted()) return;
                try {
                    const rows = await getJson('/api/v1/map/routes');
                    routes.clear();
                    for (const row of rows) {
                        if (Array.isArray(row.route_points)) routes.set(row.callsign, { points: row.route_points, next: row.route_next_index ?? 0 });
                    }
                    routesLoaded = true;
                    renderRoutes();
                } catch (error) {
                    console.warn(error);
                }
            }

            function unwrapLine(coords) {
                if (!coords.length) return coords;
                const out = [coords[0]];
                for (let i = 1; i < coords.length; i++) {
                    let [lon, lat] = coords[i];
                    const prev = out[i - 1][0];
                    while (lon - prev > 180) lon -= 360;
                    while (lon - prev < -180) lon += 360;
                    out.push([lon, lat]);
                }
                return out;
            }

            function renderRoutes() {
                const source = map.getSource('routes');
                if (!source) return;
                const selectedCallsign = state.selection?.kind === 'flight' ? state.selection.id : null;
                const lines = [];
                const points = [];

                for (const flight of state.flights) {
                    const selected = flight.callsign === selectedCallsign;
                    const category = flightCategory(flight);
                    // All routes shows traffic still going somewhere; a completed flight's route
                    // only appears when it is the one selected.
                    if (!selected && (!state.layers.routes || category === 'completed')) continue;
                    const route = routes.get(flight.callsign);
                    if (!route || route.points.length < 2) continue;

                    const colour = selected ? HIGHLIGHT_COLOUR : flightColour(flight);
                    const coords = route.points.map((p) => [p.lon, p.lat]);
                    // A flight that has not departed has flown none of it, whatever the index says.
                    const next = category === 'pending' ? 0 : Math.min(Math.max(route.next, 0), coords.length - 1);

                    // From the aircraft itself to the next waypoint and on, so the line starts where
                    // the tag is rather than at a waypoint already behind it.
                    const here = category === 'airborne' && Number.isFinite(flight.lat) ? [[flight.lon, flight.lat]] : [];
                    const remaining = unwrapLine([...here, ...coords.slice(next)]);
                    if (remaining.length > 1) {
                        lines.push({ type: 'Feature', properties: { kind: 'remaining', colour, selected },
                            geometry: { type: 'LineString', coordinates: remaining } });
                    }
                    if (selected) {
                        const flown = unwrapLine([...coords.slice(0, next + (here.length ? 0 : 1)), ...here]);
                        if (flown.length > 1 && next > 0) {
                            lines.push({ type: 'Feature', properties: { kind: 'flown', colour, selected },
                                geometry: { type: 'LineString', coordinates: flown } });
                        }
                        route.points.forEach((p, i) => points.push({ type: 'Feature',
                            properties: { name: p.n, colour, passed: i < next },
                            geometry: { type: 'Point', coordinates: [p.lon, p.lat] } }));
                    }
                }

                source.setData({ type: 'FeatureCollection', features: lines });
                map.getSource('route-points').setData({ type: 'FeatureCollection', features: points });
            }

            // ---------------- who keeps an untracked FDR current ----------------

            // The same rule the plugin applies (FdrSync.ResponsibleSectorForUntracked): the held
            // sector the aircraft is in, otherwise the held sector nearest to it by straight-line
            // distance to its boundary, ties to the first name. Only controllers who are online count.
            // Flat-earth with longitude scaled to the aircraft's latitude, as in the plugin.
            function nearestOnSector(shape, lon, lat) {
                const k = Math.cos(lat * Math.PI / 180);
                let best = { distance: Infinity, point: null };
                for (const ring of shape.boundary ?? []) {
                    if (ring.length < 3) continue;
                    let inside = false;
                    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                        const xi = (ring[i].lon - lon) * k * 60, yi = (ring[i].lat - lat) * 60;
                        const xj = (ring[j].lon - lon) * k * 60, yj = (ring[j].lat - lat) * 60;
                        if ((yi > 0) !== (yj > 0) && 0 < (xj - xi) * (0 - yi) / (yj - yi) + xi) inside = !inside;
                        const dx = xj - xi, dy = yj - yi, len2 = dx * dx + dy * dy;
                        const t = len2 === 0 ? 0 : Math.max(0, Math.min(1, -(xi * dx + yi * dy) / len2));
                        const cx = xi + t * dx, cy = yi + t * dy, d = Math.hypot(cx, cy);
                        if (d < best.distance) best = { distance: d, point: [lon + cx / (k * 60), lat + cy / 60] };
                    }
                    if (inside) return { distance: 0, point: [lon, lat] };
                }
                return best;
            }

            function responsibleFor(f) {
                if (!f || f.controlling_callsign) return null; // tracked: its controller updates it
                const at = flightPoint(f);
                if (!at) return null;
                const [lon, lat] = at;
                let best = null;
                for (const [name, owner] of state.owners) {
                    if (!state.controllers.get(owner.callsign)?.online) continue;
                    const shape = state.shapes.get(name);
                    if (!shape?.boundary?.length) continue;
                    const near = nearestOnSector(shape, lon, lat);
                    if (!near.point) continue;
                    if (!best || near.distance < best.distance || (near.distance === best.distance && name < best.sector)) {
                        best = { sector: name, owner: owner.callsign, distance: near.distance, from: [lon, lat], to: near.point };
                    }
                }
                return best;
            }

            // The flight panel's line for it: who keeps this untracked FDR current, and why.
            function responsibleBlock(f) {
                if (f.controlling_callsign) return '';
                const r = responsibleFor(f);
                if (!r) {
                    return `<div class="oz-block"><h4>Kept current by</h4><div class="oz-empty" style="padding:2px 0">Nobody - no controller online holds a sector.</div></div>`;
                }
                const why = r.distance <= 0.1
                    ? `inside ${sectorChip(r.sector)}`
                    : `nearest held sector ${sectorChip(r.sector)} <span style="color:${RESPONSIBLE_COLOUR}">${Math.round(r.distance)} nm</span>`;
                return `<div class="oz-block"><h4>Kept current by</h4>${controllerChip(r.owner)} ${why}</div>`;
            }

            function renderResponsible() {
                const source = map.getSource('responsible');
                if (!source) return;
                const sel = state.selection;
                const f = sel?.kind === 'flight' ? state.flightsByCallsign.get(sel.id) : null;
                const r = responsibleFor(f);
                const features = [];
                // Inside the sector there is no gap to draw; the panel says so instead.
                if (r && r.distance > 0.1) {
                    const [a, b] = unwrapLine([r.from, r.to]);
                    features.push({ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [a, b] } });
                    features.push({ type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: b } });
                    features.push({
                        type: 'Feature',
                        properties: { label: `${r.owner} · ${r.sector} · ${Math.round(r.distance)} nm` },
                        geometry: { type: 'Point', coordinates: [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2] },
                    });
                }
                source.setData({ type: 'FeatureCollection', features });
            }

            function setAllRoutes(on) {
                state.layers.routes = on;
                savePrefs();
                const button = document.getElementById('oz-routes-btn');
                button.classList.toggle('on', on);
                button.textContent = on ? 'Hide all routes' : 'Show all routes';
                if (on && !routesLoaded) refreshRoutes(); else { renderRoutes(); refreshRoutes(); }
            }

            function atisPopupHtml(atis) {
                const changed = new Set(atis.changed ?? []);
                const body = typeof atis.content === 'string'
                    ? `<p>${esc(atis.content)}</p>`
                    : Object.entries(atis.content ?? {}).filter(([, v]) => v !== null && v !== '')
                        .map(([k, v]) => `<p style="margin:2px 0"><strong>${changed.has(k) ? '+' : ''}${esc(k)}:</strong> ${esc(v)}</p>`).join('');
                return `<div style="max-width:260px;font-size:12px"><h3 style="margin:0 0 4px;font-size:14px">${esc(atis.icao)} ATIS ${esc(atis.atis_letter)}</h3>
                    ${atis.frequency ? `<p style="margin:2px 0;color:#94a3b8">${esc(atis.frequency)} MHz</p>` : ''}${body}
                    ${atis.issued ? `<p style="margin:4px 0 0;color:#64748b">Issued ${esc(atis.issued)}</p>` : ''}</div>`;
            }

            function renderAtis() {
                const seen = new Set();
                for (const atis of state.atis) {
                    seen.add(atis.icao);
                    let marker = atisMarkers.get(atis.icao);
                    if (!marker) {
                        const el = document.createElement('div');
                        el.className = 'oz-atis-marker';
                        marker = new mapboxgl.Marker({ element: el }).setPopup(new mapboxgl.Popup({ offset: 14 }));
                        atisMarkers.set(atis.icao, marker);
                    }
                    marker.getElement().textContent = atis.atis_letter;
                    marker.setLngLat([atis.lon, atis.lat]);
                    marker.getPopup().setHTML(atisPopupHtml(atis));
                    setMarkerVisible(marker, state.layers.atis);
                }
                for (const [icao, marker] of atisMarkers) {
                    if (!seen.has(icao)) { setMarkerVisible(marker, false); atisMarkers.delete(icao); }
                }
            }

            function renderAnnotations() {
                const annotations = state.ops?.annotations ?? [];
                const strokes = annotations.filter((a) => a.kind === 'stroke' && (a.points?.length ?? 0) > 1).map((a) => ({
                    type: 'Feature',
                    properties: { colour: colourFor(a.author?.callsign) },
                    geometry: { type: 'LineString', coordinates: a.points.map((p) => [p.lon, p.lat]) },
                }));
                const source = map.getSource('annotation-strokes');
                if (source) source.setData({ type: 'FeatureCollection', features: state.layers.annotations ? strokes : [] });

                const seen = new Set();
                for (const note of annotations.filter((a) => a.kind === 'note' && a.points?.length)) {
                    seen.add(note.id);
                    let marker = noteMarkers.get(note.id);
                    if (!marker) {
                        const el = document.createElement('div');
                        el.className = 'oz-note-marker';
                        el.addEventListener('click', (event) => {
                            event.stopPropagation();
                            if (note.author?.callsign) select({ kind: 'controller', id: note.author.callsign });
                        });
                        marker = new mapboxgl.Marker({ element: el, anchor: 'left' });
                        noteMarkers.set(note.id, marker);
                    }
                    const el = marker.getElement();
                    el.style.setProperty('--c', colourFor(note.author?.callsign));
                    el.innerHTML = `${esc(note.body)}<small>${esc(note.author?.callsign ?? '')}</small>`;
                    marker.setLngLat([note.points[0].lon, note.points[0].lat]);
                    setMarkerVisible(marker, state.layers.annotations);
                }
                for (const [id, marker] of noteMarkers) {
                    if (!seen.has(id)) { setMarkerVisible(marker, false); noteMarkers.delete(id); }
                }
            }

            // ---------------- rendering: panels ----------------

            function controllerFlights(callsign) {
                return state.flights.filter((f) => f.controlling_callsign === callsign);
            }
            function requestGroups() {
                const groups = new Map();
                for (const r of state.ops?.requests ?? []) {
                    if (!groups.has(r.group_id)) groups.set(r.group_id, { ...r, sectors: [] });
                    groups.get(r.group_id).sectors.push(r.sector);
                }
                return [...groups.values()].sort((a, b) => (!!a.rejected_at - !!b.rejected_at) || new Date(a.created_at) - new Date(b.created_at));
            }
            function presence(c) {
                if (!c) return 'off';
                if (c.online) return '';
                return 'stale';
            }

            function renderStats() {
                const ops = state.ops;
                const pending = (ops?.requests ?? []).filter((r) => !r.rejected_at);
                const pendingGroups = new Set(pending.map((r) => r.group_id)).size;
                const airborne = state.flights.filter((f) => flightCategory(f) === 'airborne');
                const tracked = airborne.filter((f) => f.controlling_callsign).length;
                const awaiting = state.flights.filter((f) => flightCategory(f) === 'pending').length;
                const completed = state.flights.filter((f) => flightCategory(f) === 'completed').length;
                const handovers = state.flights.filter((f) => isHandover(f.state)).length;
                const totalDrawn = [...state.shapes.values()].filter((s) => s.drawn).length;
                document.getElementById('oz-stats').innerHTML = [
                    ['ATC on OzServer', ops?.controllers?.length ?? 0],
                    ['Sectors held', `${ops?.ownerships?.length ?? 0}<span style="color:var(--faint);font-size:11px">/${totalDrawn}</span>`],
                    ['Pending requests', pendingGroups, pendingGroups ? 'warn' : ''],
                    ['Tags owned', `${tracked}<span style="color:var(--faint);font-size:11px">/${airborne.length} airborne</span>`],
                    ['Awaiting dep', `<span style="color:#4ade80">▲${awaiting}</span>`],
                    ['Completed', `<span style="color:#f87171">▼${completed}</span>`],
                    ['Handoffs', handovers, handovers ? 'warn' : ''],
                ].map(([label, value, cls]) => `<div class="oz-stat ${cls ?? ''}"><b>${value}</b><small>${label}</small></div>`).join('');

                const reqBadge = document.getElementById('oz-req-badge');
                reqBadge.hidden = !pendingGroups;
                reqBadge.textContent = pendingGroups;

                const live = document.getElementById('oz-live');
                live.classList.toggle('on', state.streamOpen);
                live.querySelector('span').textContent = (state.streamOpen ? 'Live' : 'Polling')
                    + (state.lastUpdate ? ` · ${zulu(state.lastUpdate)}` : '');
            }

            function renderControllers() {
                const sel = state.selection;
                const focus = selectionFocus();
                const rows = (state.ops?.controllers ?? []).map((c) => {
                    const flights = controllerFlights(c.callsign).length;
                    const incoming = (state.ops.requests ?? []).filter((r) => r.target_callsign === c.callsign && !r.rejected_at);
                    const outgoing = (state.ops.requests ?? []).filter((r) => r.requesting_callsign === c.callsign && !r.rejected_at);
                    const selected = focus?.controller === c.callsign && sel?.kind !== 'request';
                    const home = c.home_sector ? state.shapes.get(c.home_sector) : null;
                    return `
                        <button class="oz-row ${selected ? 'selected' : ''}" data-select="controller:${esc(c.callsign)}">
                            <i class="oz-swatch" style="--c:${colourFor(c.callsign)}"></i>
                            <div class="oz-main">
                                <div class="oz-title">${esc(c.callsign)} ${c.is_flow ? '<span class="oz-badge flow">FMP</span>' : ''}
                                    ${incoming.length ? `<span class="oz-badge warn" title="Requests waiting on this controller">${new Set(incoming.map((r) => r.group_id)).size} in</span>` : ''}
                                    ${outgoing.length ? `<span class="oz-badge" title="Requests this controller has raised">${new Set(outgoing.map((r) => r.group_id)).size} out</span>` : ''}</div>
                                <div class="oz-sub">${esc(home?.full_name || c.home_sector || 'covering only')}${c.frequency ? ' · ' + esc(freq(c.frequency)) : ''}</div>
                            </div>
                            <div class="oz-right">${c.sectors.length} sect<br>${flights} a/c</div>
                            <i class="oz-dot ${presence(c)}" title="${c.online ? 'Online' : 'Not heard from in ' + ago(c.last_seen_at)}"></i>
                        </button>`;
                }).join('');

                // Live only: controllers the VATSIM datafeed shows on a known position who are not
                // using OzServer - their airspace cannot appear as held above.
                const onOz = new Set((state.ops?.controllers ?? []).map((c) => c.callsign));
                const others = state.vatsimControllers.filter((c) => !c.is_ozserver && !onOz.has(c.callsign));
                const otherRows = others.map((c) => `
                    <div class="oz-row" style="cursor:default">
                        <i class="oz-swatch" style="--c:#334155"></i>
                        <div class="oz-main"><div class="oz-title">${esc(c.callsign)}</div>
                        <div class="oz-sub">${esc(c.sector_name)} · ${esc((c.frequencies ?? []).map(freq).join(', '))}</div></div>
                        <span class="oz-badge bad" title="Not connected through OzServer">no plugin</span>
                    </div>`).join('');

                document.getElementById('oz-controllers').innerHTML =
                    (rows || '<div class="oz-empty">Nobody holds a sector on this server.</div>')
                    + (otherRows ? `<div class="oz-section-title">On VATSIM without OzServer</div>${otherRows}` : '');
            }

            function renderSectorsTab() {
                const filter = document.getElementById('oz-sector-search').value.trim().toUpperCase();
                const focus = selectionFocus();
                const shapes = [...state.shapes.values()].filter((s) => {
                    if (!filter) return state.owners.has(s.name);
                    const owner = state.owners.get(s.name)?.callsign ?? '';
                    return [s.name, s.full_name, s.callsign, owner, s.type].some((v) => String(v ?? '').toUpperCase().includes(filter));
                }).sort((a, b) => a.rank - b.rank || a.name.localeCompare(b.name));

                const html = shapes.slice(0, 300).map((s) => {
                    const owner = state.owners.get(s.name);
                    const selected = state.selection?.kind === 'sector' && state.selection.id === s.name;
                    const inFocus = focus?.sectors.has(s.name);
                    return `
                        <button class="oz-row ${selected || inFocus ? 'selected' : ''}" data-select="sector:${esc(s.name)}">
                            <i class="oz-swatch" style="--c:${owner ? colourFor(owner.callsign) : UNSTAFFED_COLOUR}"></i>
                            <div class="oz-main">
                                <div class="oz-title">${esc(s.name)} <span class="oz-badge">${esc(s.type ?? '')}</span></div>
                                <div class="oz-sub">${esc(s.full_name || '')}</div>
                            </div>
                            <div class="oz-right">${owner ? esc(owner.callsign) : '<span style="color:var(--faint)">unstaffed</span>'}</div>
                        </button>`;
                }).join('');
                document.getElementById('oz-sectors').innerHTML = html
                    || `<div class="oz-empty">${filter ? 'No sector matches.' : 'No sectors held. Type to search every sector.'}</div>`;
            }

            function renderRequestsTab() {
                const groups = requestGroups();
                const html = groups.map((g) => {
                    const selected = state.selection?.kind === 'request' && state.selection.id === g.group_id;
                    return `
                        <button class="oz-row ${selected ? 'selected' : ''}" data-select="request:${esc(g.group_id)}" style="align-items:flex-start">
                            <i class="oz-swatch" style="--c:${g.rejected_at ? 'var(--bad)' : REQUEST_COLOUR};margin-top:4px"></i>
                            <div class="oz-main">
                                <div class="oz-title">${esc(g.requesting_callsign)} → ${esc(g.target_callsign)}</div>
                                <div class="oz-sub">${esc(g.sectors.slice(0, 8).join(', '))}${g.sectors.length > 8 ? ` +${g.sectors.length - 8}` : ''}</div>
                                <div style="margin-top:3px">
                                    ${g.rejected_at ? '<span class="oz-badge bad">rejected</span>' : '<span class="oz-badge warn">awaiting answer</span>'}
                                    ${g.is_flow_relief ? '<span class="oz-badge flow">FMP relief · temporary</span>' : ''}
                                </div>
                            </div>
                            <div class="oz-right">${ago(g.rejected_at ?? g.created_at)}</div>
                        </button>`;
                }).join('');
                document.getElementById('oz-requests').innerHTML = html
                    || '<div class="oz-empty">No sector requests between controllers right now.</div>';
            }

            function renderFlightsTab() {
                const filter = document.getElementById('oz-flight-search').value.trim().toUpperCase();
                const focus = selectionFocus();
                const flights = state.flights.filter((f) => !filter || [f.callsign, f.aircraft_type, f.dep_airport, f.des_airport,
                    f.controlling_callsign, f.current_sector].some((v) => String(v ?? '').toUpperCase().includes(filter)));

                // Within a controller's focus their own traffic comes first.
                flights.sort((a, b) => {
                    if (focus?.controller) {
                        const d = (b.controlling_callsign === focus.controller) - (a.controlling_callsign === focus.controller);
                        if (d) return d;
                    }
                    return (!!b.controlling_callsign - !!a.controlling_callsign) || a.callsign.localeCompare(b.callsign);
                });

                const html = flights.slice(0, 400).map((f) => {
                    const selected = state.selection?.kind === 'flight' && state.selection.id === f.callsign;
                    return `
                        <button class="oz-row ${selected ? 'selected' : ''}" data-select="flight:${esc(f.callsign)}">
                            <i class="oz-swatch" style="--c:${flightColour(f)}"></i>
                            <div class="oz-main">
                                <div class="oz-title">${esc(f.callsign)} ${categoryBadge(f)} ${isHandover(f.state) ? '<span class="oz-badge warn">handoff</span>' : ''}</div>
                                <div class="oz-sub">${esc(f.aircraft_type ?? '')} ${esc(f.dep_airport ?? '????')}→${esc(f.des_airport ?? '????')}</div>
                            </div>
                            <div class="oz-right">${esc(f.controlling_callsign ?? 'no owner')}<br>${flightCategory(f) === 'airborne' && f.altitude != null ? esc(f.altitude) + 'ft' : ''}</div>
                        </button>`;
                }).join('');
                document.getElementById('oz-flights').innerHTML = html || '<div class="oz-empty">No flights.</div>';
            }

            const FEED_ICONS = { claim: '🟢', release: '⚪', transfer: '🔁', request: '🟠', accept: '✅', reject: '⛔', withdraw: '↩️',
                handoff: '✈️', offer: '📡', depart: '🛫', complete: '🛬', quiet: '⚠️', back: '🟢', note: '📝', atis: 'ℹ️' };

            function renderActivity() {
                const html = state.feed.map((item) => `
                    <div class="oz-feed-item" ${item.select ? `data-select="${esc(item.select)}"` : ''}>
                        <time>${zulu(item.at).slice(0, 8)}</time>
                        <span class="oz-icon">${FEED_ICONS[item.type] ?? '•'}</span>
                        <span>${item.html}</span>
                    </div>`).join('');
                document.getElementById('oz-activity').innerHTML = html
                    || '<div class="oz-empty">Changes appear here as they happen - sectors claimed, released and handed over, requests, aircraft handoffs.</div>';
                const badge = document.getElementById('oz-feed-badge');
                badge.hidden = !state.unseenFeed || state.activeTab === 'activity';
                badge.textContent = state.unseenFeed > 99 ? '99+' : state.unseenFeed;
            }

            function flightDetail(f) {
                const squawk = f.assigned_ssr_code != null ? String(f.assigned_ssr_code).padStart(4, '0') : null;
                const cfl = f.cfl_lower && f.cfl_upper && f.cfl_lower !== f.cfl_upper ? `${f.cfl_lower}-${f.cfl_upper}` : (f.cfl_lower ?? f.cfl_upper);
                const kv = [
                    ['Type', [f.aircraft_type, f.aircraft_wake ? '/' + f.aircraft_wake : ''].join('')],
                    ['Route', `${f.dep_airport ?? '????'} → ${f.des_airport ?? '????'}`],
                    ['SID/STAR · Rwy', [f.sid_star_string, f.runway_string ?? f.departure_runway].filter(Boolean).join(' ')],
                    ['Level', [cfl ? 'CFL ' + cfl : null, f.rfl ? 'RFL ' + f.rfl : null].filter(Boolean).join(' · ')],
                    ['Now', `${f.altitude ?? '?'} ft · ${f.ground_speed ?? '?'} kt${f.heading != null ? ' · ' + f.heading + '°' : ''}`],
                    ['Squawk', squawk],
                    ['Times', [f.atd ? 'ATD ' + hhmm(f.atd) : f.etd ? 'ETD ' + hhmm(f.etd) : null, f.eet_minutes ? `EET ${f.eet_minutes}m` : null].filter(Boolean).join(' · ')],
                    ['Rules', f.flight_rules],
                    ['State', f.state ? f.state.replace('STATE_', '').toLowerCase() : null],
                    ['Last update', f.last_seen_at ? `${ago(f.last_seen_at)} ago` : null],
                ].filter(([, v]) => v);
                return `
                    <div class="oz-detail-head">
                        <i class="oz-swatch" style="--c:${flightColour(f)}"></i>
                        <div><div class="oz-kind">Flight</div><h2>${esc(f.callsign)}</h2></div>
                        <button class="oz-close" data-close>×</button>
                    </div>
                    <div class="oz-block"><h4>Jurisdiction</h4>${controllerChip(f.controlling_callsign)}
                        ${f.current_sector ? ' in ' + sectorChip(f.current_sector) : ''}
                        ${isHandover(f.state) ? '<div class="oz-note-box warn">Handoff in progress - being offered to the next controller.</div>' : ''}</div>
                    ${responsibleBlock(f)}
                    <dl class="oz-kv">${kv.map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`).join('')}</dl>
                    ${f.route ? `<div class="oz-block"><h4>Filed route</h4><div class="oz-route">${esc(f.route)}</div></div>` : ''}
                    ${f.remarks ? `<div class="oz-block"><h4>Remarks</h4><div class="oz-route">${esc(f.remarks)}</div></div>` : ''}`;
            }

            function flightsTable(flights) {
                if (!flights.length) return '<div class="oz-empty">None.</div>';
                return `<table class="oz-table">${flights.slice(0, 60).map((f) => `
                    <tr data-select="flight:${esc(f.callsign)}"><td>${esc(f.callsign)}</td>
                    <td>${esc(f.dep_airport ?? '')}→${esc(f.des_airport ?? '')}</td>
                    <td>${f.altitude != null ? esc(f.altitude) : ''}</td>
                    <td>${isHandover(f.state) ? '<span class="oz-badge warn">handoff</span>' : ''}</td></tr>`).join('')}</table>`;
            }

            function requestsBlock(rows, title) {
                if (!rows.length) return '';
                const groups = new Map();
                for (const r of rows) {
                    if (!groups.has(r.group_id)) groups.set(r.group_id, { ...r, sectors: [] });
                    groups.get(r.group_id).sectors.push(r.sector);
                }
                return `<div class="oz-block"><h4>${esc(title)}</h4>${[...groups.values()].map((g) => `
                    <div class="oz-row" data-select="request:${esc(g.group_id)}" style="padding:5px 6px">
                        <div class="oz-main"><div class="oz-title" style="font-size:12px">${esc(g.requesting_callsign)} → ${esc(g.target_callsign)}</div>
                        <div class="oz-sub">${esc(g.sectors.join(', '))}</div></div>
                        ${g.rejected_at ? '<span class="oz-badge bad">rejected</span>' : '<span class="oz-badge warn">pending</span>'}
                        ${g.is_flow_relief ? '<span class="oz-badge flow">FMP</span>' : ''}
                    </div>`).join('')}</div>`;
            }

            function controllerDetail(callsign) {
                const c = state.controllers.get(callsign);
                if (!c) {
                    return `<div class="oz-detail-head"><div><div class="oz-kind">Controller</div><h2>${esc(callsign)}</h2></div>
                        <button class="oz-close" data-close>×</button></div>
                        <div class="oz-empty">Holds no sectors on this server right now.</div>
                        ${flightsTableBlock(controllerFlights(callsign))}`;
                }
                const home = c.home_sector ? state.shapes.get(c.home_sector) : null;
                const requests = state.ops.requests ?? [];
                const notes = (state.ops.annotations ?? []).filter((a) => a.author?.callsign === callsign);
                const covering = c.sectors.filter((n) => n !== c.home_sector && !(home?.responsible_sectors ?? []).includes(n));
                return `
                    <div class="oz-detail-head">
                        <i class="oz-swatch" style="--c:${colourFor(c.callsign)}"></i>
                        <div><div class="oz-kind">Controller ${c.is_flow ? '<span class="oz-badge flow">FMP</span>' : ''}</div><h2>${esc(c.callsign)}</h2></div>
                        <button class="oz-close" data-close>×</button>
                    </div>
                    <dl class="oz-kv">
                        <dt>CID</dt><dd>${esc(c.cid)}</dd>
                        <dt>Position</dt><dd>${home ? esc(home.full_name || home.name) : '<span style="color:var(--faint)">none - covering others only</span>'}</dd>
                        ${c.frequency ? `<dt>Frequency</dt><dd>${esc(freq(c.frequency))}</dd>` : ''}
                        <dt>Presence</dt><dd>${c.online ? '<span style="color:var(--good)">online</span>' : `<span style="color:var(--warn)">not heard from for ${ago(c.last_seen_at)}</span>`}</dd>
                    </dl>
                    ${c.is_flow && c.sectors.some((n) => state.shapes.get(n)?.type !== 'FMP')
                        ? '<div class="oz-note-box flow">FMP is covering ENR/TMA airspace as temporary relief for the controller who normally holds it.</div>' : ''}
                    <div class="oz-block"><h4>Holds ${c.sectors.length} sector${c.sectors.length === 1 ? '' : 's'}</h4>${sectorList(c.sectors, c.home_sector)}</div>
                    ${covering.length ? `<div class="oz-block"><h4>Beyond their own position</h4>${sectorList(covering)}</div>` : ''}
                    ${requestsBlock(requests.filter((r) => r.target_cid === c.cid && r.target_callsign === c.callsign), 'Asked of them')}
                    ${requestsBlock(requests.filter((r) => r.requesting_cid === c.cid && r.requesting_callsign === c.callsign), 'Their requests')}
                    ${flightsTableBlock(controllerFlights(c.callsign))}
                    ${notes.length ? `<div class="oz-block"><h4>Notes & drawings</h4>${notes.filter((n) => n.kind === 'note').length} notes · ${notes.filter((n) => n.kind === 'stroke').length} drawings</div>` : ''}`;
            }
            function flightsTableBlock(flights) {
                return `<div class="oz-block"><h4>Controlling ${flights.length} flight${flights.length === 1 ? '' : 's'}</h4>${flightsTable(flights)}</div>`;
            }

            function sectorDetail(name) {
                const s = state.shapes.get(name);
                if (!s) return `<div class="oz-detail-head"><h2>${esc(name)}</h2><button class="oz-close" data-close>×</button></div><div class="oz-empty">Unknown sector.</div>`;
                const owner = state.owners.get(name);
                const c = owner ? state.controllers.get(owner.callsign) : null;
                const requests = (state.ops?.requests ?? []).filter((r) => r.sector === name);
                const children = s.responsible_sectors ?? [];
                const parents = state.coveredBy.get(name) ?? [];
                const inside = state.flights.filter((f) => f.current_sector === name);
                const others = clickStack.length > 1 && clickStack.includes(name) ? clickStack.filter((n) => n !== name) : [];

                return `
                    <div class="oz-detail-head">
                        <i class="oz-swatch" style="--c:${owner ? colourFor(owner.callsign) : UNSTAFFED_COLOUR}"></i>
                        <div><div class="oz-kind">Sector · ${esc(s.type ?? '')}</div><h2>${esc(s.name)}</h2>
                            <div style="color:var(--muted)">${esc(s.full_name || '')}</div></div>
                        <button class="oz-close" data-close>×</button>
                    </div>
                    <dl class="oz-kv">
                        ${s.callsign ? `<dt>Position</dt><dd>${esc(s.callsign)}</dd>` : ''}
                        ${s.frequency ? `<dt>Frequency</dt><dd>${esc(freq(s.frequency))}</dd>` : ''}
                        <dt>Held by</dt><dd>${owner ? controllerChip(owner.callsign) : '<span style="color:var(--faint)">unstaffed</span>'}</dd>
                        ${owner?.since ? `<dt>Since</dt><dd>${hhmm(owner.since)} (${ago(owner.since)} ago)</dd>` : ''}
                    </dl>
                    ${owner && isFlow(owner.callsign) && s.type !== 'FMP' ? '<div class="oz-note-box flow">Held by an FMP as temporary relief cover.</div>' : ''}
                    ${c && c.sectors.length > 1 ? `<div class="oz-block"><h4>${esc(c.callsign)} also holds (${c.sectors.length - 1})</h4>${sectorList(c.sectors.filter((n) => n !== name))}</div>` : ''}
                    ${children.length ? `<div class="oz-block"><h4>Subsectors (combined with this position)</h4>${sectorList(children)}</div>` : ''}
                    ${parents.length ? `<div class="oz-block"><h4>Part of</h4>${sectorList(parents)}</div>` : ''}
                    ${requestsBlock(requests, 'Requests for this sector')}
                    <div class="oz-block"><h4>${inside.length} flight${inside.length === 1 ? '' : 's'} in this sector</h4>${flightsTable(inside)}</div>
                    ${others.length ? `<div class="oz-also">Also under the click: ${others.map((n) => sectorChip(n)).join('')}</div>` : ''}`;
            }

            function requestDetail(groupId) {
                const rows = (state.ops?.requests ?? []).filter((r) => r.group_id === groupId);
                if (!rows.length) return `<div class="oz-detail-head"><div><div class="oz-kind">Request</div><h2>Answered</h2></div>
                    <button class="oz-close" data-close>×</button></div><div class="oz-empty">This request is no longer open - see Activity for how it ended.</div>`;
                const r = rows[0];
                return `
                    <div class="oz-detail-head">
                        <i class="oz-swatch" style="--c:${REQUEST_COLOUR}"></i>
                        <div><div class="oz-kind">Sector request</div><h2>${rows.length} sector${rows.length === 1 ? '' : 's'}</h2></div>
                        <button class="oz-close" data-close>×</button>
                    </div>
                    <dl class="oz-kv">
                        <dt>Requested by</dt><dd>${controllerChip(r.requesting_callsign)}</dd>
                        <dt>Asked of</dt><dd>${controllerChip(r.target_callsign)}</dd>
                        <dt>Raised</dt><dd>${hhmm(r.created_at)} (${ago(r.created_at)} ago)</dd>
                        <dt>Status</dt><dd>${r.rejected_at ? `<span style="color:var(--bad)">rejected ${hhmm(r.rejected_at)}</span>` : '<span style="color:var(--warn)">awaiting answer</span>'}</dd>
                    </dl>
                    ${r.is_flow_relief ? '<div class="oz-note-box flow">FMP relief cover - a temporary hand-over so the controller can take a break. FMP should not hold ENR/TMA airspace for extended periods.</div>' : ''}
                    <div class="oz-block"><h4>Sectors</h4>${sectorList(rows.map((x) => x.sector))}</div>`;
            }

            function renderDetail() {
                const panel = document.getElementById('oz-detail');
                const sel = state.selection;
                if (!sel) { panel.hidden = true; panel.innerHTML = ''; return; }
                const scroll = panel.scrollTop;
                panel.hidden = false;
                if (sel.kind === 'controller') panel.innerHTML = controllerDetail(sel.id);
                else if (sel.kind === 'sector') panel.innerHTML = sectorDetail(sel.id);
                else if (sel.kind === 'request') panel.innerHTML = requestDetail(sel.id);
                else if (sel.kind === 'flight') {
                    const f = state.flightsByCallsign.get(sel.id);
                    panel.innerHTML = f ? flightDetail(f)
                        : `<div class="oz-detail-head"><div><div class="oz-kind">Flight</div><h2>${esc(sel.id)}</h2></div><button class="oz-close" data-close>×</button></div><div class="oz-empty">No longer on the map.</div>`;
                }
                panel.scrollTop = scroll;
            }

            // Each part draws independently: one failing (bad data from a client, a map not ready
            // yet) must not leave every panel after it stale.
            function renderAll() {
                for (const step of [applySectorStates, renderAircraft, renderAnnotations, renderAtis, renderStats,
                    renderControllers, renderSectorsTab, renderRequestsTab, renderFlightsTab, renderActivity, renderDetail, renderRoutes, renderResponsible]) {
                    try { step(); } catch (error) { console.warn(step.name, error); }
                }
            }

            // ---------------- activity feed (diffing successive snapshots) ----------------

            function pushFeed(type, html, select = null) {
                state.feed.unshift({ at: Date.now(), type, html, select });
                if (state.feed.length > FEED_LIMIT) state.feed.length = FEED_LIMIT;
                if (state.activeTab !== 'activity') state.unseenFeed++;
            }
            const b = (text) => `<b>${esc(text)}</b>`;
            const cs = (callsign) => `<b style="color:${colourFor(callsign)}">${esc(callsign)}</b>`;
            function listNames(names) {
                const sorted = [...names].sort();
                return b(sorted.slice(0, 6).join(', ')) + (sorted.length > 6 ? ` +${sorted.length - 6}` : '');
            }

            function diffOps(prev, next) {
                if (!prev) return;
                const before = new Map(prev.ownerships.map((o) => [o.sector, o.callsign]));
                const after = new Map(next.ownerships.map((o) => [o.sector, o.callsign]));

                // Sector ownership: grouped by (from, to) so one group claim reads as one line.
                const moves = new Map();
                for (const sector of new Set([...before.keys(), ...after.keys()])) {
                    const from = before.get(sector) ?? null, to = after.get(sector) ?? null;
                    if (from === to) continue;
                    const key = `${from}|${to}`;
                    if (!moves.has(key)) moves.set(key, { from, to, sectors: [] });
                    moves.get(key).sectors.push(sector);
                }

                // Requests: which closed, and how.
                const prevReq = new Map(prev.requests.map((r) => [r.id, r]));
                const nextReq = new Map(next.requests.map((r) => [r.id, r]));
                const closedGroups = new Map();
                for (const [id, r] of prevReq) {
                    if (nextReq.has(id) || r.rejected_at) continue;
                    if (!closedGroups.has(r.group_id)) closedGroups.set(r.group_id, { ...r, sectors: [] });
                    closedGroups.get(r.group_id).sectors.push(r.sector);
                }
                const acceptedMoves = new Set();
                for (const g of closedGroups.values()) {
                    const accepted = g.sectors.some((s) => after.get(s) === g.requesting_callsign);
                    if (accepted) {
                        pushFeed('accept', `${cs(g.target_callsign)} accepted ${cs(g.requesting_callsign)}'s request for ${listNames(g.sectors)}`, `controller:${g.requesting_callsign}`);
                        acceptedMoves.add(`${g.target_callsign}|${g.requesting_callsign}`);
                    } else {
                        pushFeed('withdraw', `${cs(g.requesting_callsign)}'s request for ${listNames(g.sectors)} was withdrawn`, `controller:${g.requesting_callsign}`);
                    }
                }

                for (const m of moves.values()) {
                    if (!m.from) pushFeed('claim', `${cs(m.to)} took ${listNames(m.sectors)}`, `controller:${m.to}`);
                    else if (!m.to) pushFeed('release', `${cs(m.from)} released ${listNames(m.sectors)}`, `sector:${m.sectors[0]}`);
                    else if (!acceptedMoves.has(`${m.from}|${m.to}`)) pushFeed('transfer', `${listNames(m.sectors)} moved ${cs(m.from)} → ${cs(m.to)}`, `controller:${m.to}`);
                }

                const newGroups = new Map();
                for (const [id, r] of nextReq) {
                    const old = prevReq.get(id);
                    if (!old) {
                        if (!newGroups.has(r.group_id)) newGroups.set(r.group_id, { ...r, sectors: [] });
                        newGroups.get(r.group_id).sectors.push(r.sector);
                    } else if (!old.rejected_at && r.rejected_at && !newGroups.has('rej:' + r.group_id)) {
                        newGroups.set('rej:' + r.group_id, { ...r, rejected: true, sectors: next.requests.filter((x) => x.group_id === r.group_id).map((x) => x.sector) });
                    }
                }
                for (const g of newGroups.values()) {
                    if (g.rejected) {
                        pushFeed('reject', `${cs(g.target_callsign)} rejected ${cs(g.requesting_callsign)}'s request for ${listNames(g.sectors)}`, `request:${g.group_id}`);
                    } else if (!g.rejected_at) {
                        pushFeed('request', `${cs(g.requesting_callsign)} asked ${cs(g.target_callsign)} for ${listNames(g.sectors)}${g.is_flow_relief ? ' <span class="oz-badge flow">FMP relief</span>' : ''}`, `request:${g.group_id}`);
                    }
                }

                // Presence.
                const prevCtl = new Map(prev.controllers.map((c) => [c.callsign, c]));
                for (const c of next.controllers) {
                    const old = prevCtl.get(c.callsign);
                    if (!old) continue;
                    if (old.online && !c.online) pushFeed('quiet', `${cs(c.callsign)} has stopped responding - their sectors are held until the grace period ends`, `controller:${c.callsign}`);
                    if (!old.online && c.online) pushFeed('back', `${cs(c.callsign)} is responding again`, `controller:${c.callsign}`);
                }

                // Notes and drawings.
                const prevAnn = new Set(prev.annotations.map((a) => a.id));
                const nextAnn = new Set(next.annotations.map((a) => a.id));
                for (const a of next.annotations) {
                    if (!prevAnn.has(a.id)) pushFeed('note', `${cs(a.author?.callsign)} ${a.kind === 'note' ? 'added a note: ' + b(String(a.body ?? '').slice(0, 60)) : 'drew on the map'}`, `controller:${a.author?.callsign}`);
                }
                const removed = prev.annotations.filter((a) => !nextAnn.has(a.id));
                if (removed.length) pushFeed('note', `${removed.length} note${removed.length === 1 ? '' : 's'}/drawing${removed.length === 1 ? '' : 's'} removed`);
            }

            function diffFlights(prev, next) {
                if (!prev) return;
                for (const f of next) {
                    const old = prev.get(f.callsign);
                    if (!old) continue;
                    if (old.controlling_callsign !== f.controlling_callsign) {
                        const from = old.controlling_callsign, to = f.controlling_callsign;
                        const text = !from ? `${cs(to)} picked up ${b(f.callsign)}`
                            : !to ? `${cs(from)} dropped ${b(f.callsign)}`
                            : `${b(f.callsign)} handed ${cs(from)} → ${cs(to)}`;
                        pushFeed('handoff', text, `flight:${f.callsign}`);
                    } else if (!isHandover(old.state) && isHandover(f.state)) {
                        pushFeed('offer', `${cs(f.controlling_callsign ?? '?')} is handing off ${b(f.callsign)}`, `flight:${f.callsign}`);
                    }
                    const before = flightCategory(old), after = flightCategory(f);
                    if (before === 'pending' && after === 'airborne') {
                        pushFeed('depart', `${b(f.callsign)} departed ${b(f.dep_airport ?? '????')}`, `flight:${f.callsign}`);
                    } else if (before !== 'completed' && after === 'completed') {
                        pushFeed('complete', `${b(f.callsign)} completed at ${b(f.des_airport ?? '????')}`, `flight:${f.callsign}`);
                    }
                }
            }

            function diffAtis(prev, next) {
                if (!prev) return;
                for (const a of next) {
                    const old = prev.get(a.icao);
                    if (old && old !== a.atis_letter) pushFeed('atis', `${b(a.icao)} ATIS now ${b(a.atis_letter)}`);
                }
            }

            // ---------------- data ----------------

            async function refreshOps() {
                try {
                    const ops = await getJson('/api/v1/map/ops');
                    diffOps(state.ops, ops);
                    state.ops = ops;
                    state.owners = new Map(ops.ownerships.map((o) => [o.sector, o]));
                    state.controllers = new Map(ops.controllers.map((c) => [c.callsign, c]));
                    state.lastUpdate = Date.now();
                    renderAll();
                } catch (error) {
                    console.warn(error);
                }
            }

            async function refreshAircraft() {
                try {
                    const flights = await getJson('/api/v1/map/aircraft');
                    const prev = state.flights.length || state.flightsLoaded ? new Map(state.flights.map((f) => [f.callsign, f])) : null;
                    diffFlights(prev, flights);
                    state.flights = flights;
                    state.flightsLoaded = true;
                    state.flightsByCallsign = new Map(flights.map((f) => [f.callsign, f]));
                    state.lastUpdate = Date.now();
                    renderAll();
                } catch (error) {
                    console.warn(error);
                }
            }

            async function refreshAtis() {
                try {
                    const atis = await getJson('/api/v1/map/atis');
                    diffAtis(state.atisLoaded ? new Map(state.atis.map((a) => [a.icao, a.atis_letter])) : null, atis);
                    state.atis = atis;
                    state.atisLoaded = true;
                    renderAtis();
                    renderActivity();
                } catch (error) {
                    console.warn(error);
                }
            }

            async function refreshVatsimControllers() {
                try {
                    state.vatsimControllers = await getJson('/api/v1/map/controllers');
                    renderControllers();
                } catch (error) {
                    console.warn(error);
                }
            }

            function refreshAll() {
                refreshOps();
                refreshAircraft();
                refreshAtis();
                refreshVatsimControllers();
                refreshRoutes();
            }

            function resetForServer() {
                state.ops = null;
                state.owners = new Map();
                state.controllers = new Map();
                state.flights = [];
                state.flightsByCallsign = new Map();
                state.flightsLoaded = false;
                state.atis = [];
                state.atisLoaded = false;
                state.feed = [];
                state.unseenFeed = 0;
                state.selection = null;
                routes.clear();
                routesLoaded = false;
                renderAll();
            }

            function coalesce(fn, waitMs) {
                let timer = null;
                return () => {
                    if (timer !== null) return;
                    timer = setTimeout(() => { timer = null; fn(); }, waitMs);
                };
            }

            // The API pushes bare "something changed" signals per server; the page re-reads the
            // matching endpoint. The interval poll stays underneath as the fallback.
            let eventSource = null;
            function subscribeToEvents() {
                if (!window.EventSource) return;
                if (eventSource) eventSource.close();
                state.streamOpen = false;
                eventSource = new EventSource(apiUrl('/api/v1/events'));
                eventSource.onopen = () => { state.streamOpen = true; renderStats(); };
                eventSource.onerror = () => { state.streamOpen = false; renderStats(); };
                const onOps = coalesce(refreshOps, 250);
                eventSource.addEventListener('sectors', onOps);
                eventSource.addEventListener('annotations', onOps);
                eventSource.addEventListener('fdr', coalesce(refreshAircraft, 1000));
                // Routes change far less often than positions.
                eventSource.addEventListener('fdr', coalesce(refreshRoutes, 5000));
                eventSource.addEventListener('atis', coalesce(refreshAtis, 250));
            }

            // ---------------- UI wiring ----------------

            const serverSelect = document.getElementById('oz-server-select');
            function populateServerOptions(servers) {
                serverSelect.innerHTML = servers.map((s) => `<option value="${esc(s.value)}">${esc(s.label)}</option>`).join('');
                if (!servers.some((s) => s.value === currentServer)) currentServer = servers[0]?.value ?? 'live';
                serverSelect.value = currentServer;
            }
            populateServerOptions(DEFAULT_SERVERS);
            getJson('/api/v1/servers', false)
                .then((servers) => { if (Array.isArray(servers) && servers.length) populateServerOptions(servers); })
                .catch(() => {});
            serverSelect.addEventListener('change', () => {
                currentServer = serverSelect.value;
                resetForServer();
                writeHash();
                subscribeToEvents();
                refreshAll();
            });

            document.getElementById('oz-tabs').addEventListener('click', (event) => {
                const button = event.target.closest('[data-tab]');
                if (!button) return;
                state.activeTab = button.dataset.tab;
                if (state.activeTab === 'activity') state.unseenFeed = 0;
                document.querySelectorAll('#oz-tabs [data-tab]').forEach((b) => b.classList.toggle('active', b === button));
                document.querySelectorAll('.oz-tab-body').forEach((body) => { body.hidden = body.dataset.body !== state.activeTab; });
                renderActivity();
            });

            // One delegated handler for every chip, row and feed line that names something.
            document.body.addEventListener('click', (event) => {
                if (event.target.closest('[data-close]')) { select(null); return; }
                const target = event.target.closest('[data-select]');
                if (!target) return;
                const value = target.dataset.select;
                const split = value.indexOf(':');
                const kind = value.slice(0, split), id = value.slice(split + 1);
                if (!id || id === 'undefined') return;
                const same = state.selection?.kind === kind && state.selection?.id === id;
                select(same && target.closest('.oz-sidebar') ? null : { kind, id });
            });

            document.getElementById('oz-sector-search').addEventListener('input', renderSectorsTab);
            document.getElementById('oz-flight-search').addEventListener('input', renderFlightsTab);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !event.target.closest('input')) select(null);
            });

            // Layer checkboxes and position filters both start from the saved preferences, and every
            // change is written straight back to the cookie.
            for (const input of document.querySelectorAll('#oz-layers [data-layer]')) {
                input.checked = !!state.layers[input.dataset.layer];
            }
            document.body.classList.toggle('show-ac-labels', !!state.layers.acLabels);

            function renderFilters() {
                for (const group of ['online', 'offline']) {
                    const holder = document.querySelector(`[data-filter-group="${group}"]`);
                    holder.parentElement.toggleAttribute('data-offline', group === 'offline');
                    holder.innerHTML = CATEGORIES.map((c) => `<button type="button" class="oz-toggle ${state[group][c.id] ? 'on' : ''}"
                        data-filter="${group}:${c.id}" title="${esc(c.title)} - ${group} sectors">${esc(c.label)}</button>`).join('');
                }
            }
            renderFilters();

            document.getElementById('oz-filters').addEventListener('click', (event) => {
                const button = event.target.closest('[data-filter]');
                if (!button) return;
                const [group, category] = button.dataset.filter.split(':');
                state[group][category] = !state[group][category];
                savePrefs();
                renderFilters();
                applySectorStates();
            });

            document.getElementById('oz-layers').addEventListener('change', (event) => {
                const layer = event.target.dataset.layer;
                if (!layer) return;
                state.layers[layer] = event.target.checked;
                savePrefs();
                applyLayerVisibility();
                if (layer === 'requests') lastSectorStates.clear();
                renderAll();
            });

            function applyLayerVisibility() {
                document.body.classList.toggle('show-ac-labels', !!state.layers.acLabels);
                if (map.getLayer('sectors-label')) {
                    map.setLayoutProperty('sectors-label', 'visibility', state.layers.labels ? 'visible' : 'none');
                }
            }

            // Keep the "ago" timers in the panels moving between data updates.
            setInterval(() => {
                renderRequestsTab();
                if (state.selection) renderDetail();
                renderStats();
            }, 5000);

            // Start-up does not wait for the map's 'load' (which waits for every base-map tile): the
            // panels fill as soon as the data arrives, and the sector layers go in the moment the
            // style itself is usable.
            const shapesReady = getJson('/api/v1/map/sector-shapes', false).catch((error) => { console.warn(error); return []; });
            const styleReady = new Promise((resolve) => {
                if (map.isStyleLoaded()) resolve(); else map.once('style.load', resolve);
            });

            (async () => {
                const shapes = await shapesReady;
                // Shapes first even without the map: sector names, types and subsectors drive the panels.
                for (const shape of shapes) {
                    state.shapes.set(shape.name, { ...shape, rank: TYPE_RANK[shape.type] ?? 2, drawn: false, bbox: null, area: Infinity });
                }
                await Promise.all([refreshOps(), refreshAircraft(), refreshAtis(), refreshVatsimControllers()]);
                const fromHash = readHash();
                if (fromHash) select(fromHash, { fromHash: true, fly: false });
                setInterval(refreshAll, POLL_INTERVAL_MS);
                setAllRoutes(!!state.layers.routes);
                document.getElementById('oz-routes-btn').addEventListener('click', () => setAllRoutes(!state.layers.routes));
                subscribeToEvents();

                await styleReady;
                loadShapes(shapes);
                applyLayerVisibility();
                renderAll();
                if (state.selection) flyToSelection();
            })();
        </script>
    </body>
</html>
