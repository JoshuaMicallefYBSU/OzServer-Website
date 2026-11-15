<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OzServer | vatSys Server Management</title>
        <meta name="description" content="OzServer keeps vatSys sector ownership, tags and ATIS in sync across every VATPAC controller.">
        <style>
            /* Panels, badges and type as on the live map (map.blade.php). The page itself is a vatSys
               scope: the Australia dataset's own ASDBackground and Infill greys (Colours.xml). */
            :root {
                --bg: rgba(2, 6, 23, 0.86);
                --line: rgba(255, 255, 255, 0.09);
                --text: #e2e8f0;
                --muted: #94a3b8;
                --faint: #64748b;
                --accent: #22d3ee;
                --warn: #fb923c;
                --bad: #f87171;
                --good: #4ade80;
                --flow: #c4b5fd;
                --scope: rgb(127, 127, 127);   /* ASDBackground */
                --infill: rgb(133, 133, 133);  /* Infill */
                --on-scope: #0b0f14;           /* text sitting straight on the grey */
                --on-scope-muted: #1f2933;
                --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            }
            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; }
            body { margin: 0; min-height: 100vh; background: var(--scope); color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.55; }
            a { color: inherit; }
            p { margin: 0; }

            /* ---------- background scope: sectors + live traffic ---------- */
            .tag-field {
                position: fixed;
                inset: 0;
                z-index: -10;
                overflow: hidden;
                pointer-events: none;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            /* A square viewport sized to the smaller of width/height, so it always fits fully
               inside the window with nothing cropped - matching the map's own square (0-100)
               viewBox. Aircraft are positioned within this same box (not the full window), so they
               stay aligned with the sector shapes at every window size. */
            .map-viewport {
                position: relative;
                width: 100vmin;
                height: 100vmin;
            }
            .oz-map {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                display: block;
            }
            .oz-sector-static {
                fill: var(--infill);
                stroke: rgba(80, 80, 80, 0.8);
                stroke-width: 0.15;
            }
            .oz-sector-active {
                fill: rgb(160, 170, 170);
                stroke: rgb(215, 215, 215);
                stroke-width: 0.3;
                opacity: 0.15;
                /* 4 batches (see HomeController::ACTIVATED_SECTOR_GROUPS) x 5 slots x 2.8s/slot
                   = 56s. Percentages below are the original single-batch 14s timing (15%/50%/65%)
                   rescaled onto that 56s cycle, so each batch gets an exclusive 14s slice and no
                   more than MAX_ACTIVE_GROUP_SIZE sectors are ever lit at once. */
                animation: sector-activate 56s ease-in-out infinite;
            }
            @keyframes sector-activate {
                0%, 100% { opacity: 0.15; }
                3.75% { opacity: 1; }
                12.5% { opacity: 1; }
                16.25% { opacity: 0.15; }
            }

            /* Live aircraft, drawn the way vatSys draws a non-jurisdiction track: black on grey. */
            .ac-leaders { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
            .ac-leaders line { stroke: #000; stroke-width: 1; opacity: 0.7; }
            .tag-target {
                position: absolute;
                width: 11px;
                height: 11px;
                margin: -5.5px 0 0 -5.5px;
                border-radius: 9999px;
                border: 1px solid #000;
            }
            .tag-target::before,
            .tag-target::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                background: #000;
            }
            .tag-target::before { width: 1px; height: 6px; transform: translate(-50%, -50%); }
            .tag-target::after { width: 6px; height: 1px; transform: translate(-50%, -50%); }
            .ac-label {
                position: absolute;
                font-family: var(--mono);
                font-size: 11px;
                line-height: 1.25;
                white-space: pre;
                color: #000;
            }
            .ac-label[hidden] { display: none; }
            @media (prefers-reduced-motion: reduce) {
                .oz-sector-active { animation: none; opacity: 0.5; }
                html { scroll-behavior: auto; }
            }

            /* ---------- panels (as on the live map) ---------- */
            .oz-panel {
                background: var(--bg); border: 1px solid var(--line); border-radius: 12px;
                backdrop-filter: blur(6px); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            }
            .oz-wrap { max-width: 1040px; margin: 0 auto; padding: 0 20px; }

            .oz-topbar {
                position: sticky; top: 12px; z-index: 5; margin: 12px auto 0; max-width: 1000px;
                height: 48px; display: flex; align-items: center; gap: 18px; padding: 0 14px;
            }
            .oz-brand { font-weight: 700; letter-spacing: 0.04em; white-space: nowrap; text-decoration: none; }
            .oz-brand span { color: var(--accent); }
            .oz-nav { display: flex; gap: 16px; flex: 1; }
            .oz-nav a { color: var(--muted); text-decoration: none; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; }
            .oz-nav a:hover { color: var(--text); }
            .oz-btn {
                display: inline-flex; align-items: center; gap: 6px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 6px; padding: 5px 10px; white-space: nowrap; font-size: 12px; text-decoration: none; cursor: pointer;
            }
            .oz-btn:hover { border-color: rgba(255, 255, 255, 0.35); }
            .oz-btn.on { background: rgba(34, 211, 238, 0.15); border-color: rgba(34, 211, 238, 0.6); color: var(--accent); }

            .oz-live { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 11px; white-space: nowrap; }
            .oz-live i { width: 8px; height: 8px; border-radius: 50%; background: var(--faint); display: inline-block; }
            .oz-live.on i { background: var(--good); box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.2); }

            .oz-badge {
                display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
                padding: 2px 6px; border-radius: 4px; background: rgba(255, 255, 255, 0.08); color: var(--muted);
            }
            .oz-badge.accent { background: rgba(34, 211, 238, 0.15); color: var(--accent); }
            .oz-badge.warn { background: rgba(251, 146, 60, 0.18); color: var(--warn); }
            .oz-badge.good { background: rgba(74, 222, 128, 0.15); color: var(--good); }
            .oz-badge.flow { background: rgba(167, 139, 250, 0.18); color: var(--flow); }

            /* ---------- hero ---------- */
            .oz-hero { margin-top: 56px; padding: 28px 30px; max-width: 700px; }
            .oz-hero h1 { margin: 14px 0 0; font-size: clamp(28px, 4.6vw, 42px); line-height: 1.12; letter-spacing: -0.01em; font-weight: 700; }
            .oz-hero h1 span { color: var(--accent); }
            .oz-hero p { margin-top: 16px; font-size: 15px; color: var(--muted); }
            .oz-hero .oz-actions { margin-top: 22px; display: flex; gap: 10px; flex-wrap: wrap; }
            .oz-hero .oz-btn { padding: 8px 14px; font-size: 13px; }

            /* ---------- live status strip: full container width ---------- */
            .oz-stats { margin-top: 14px; display: flex; align-items: center; gap: 12px 32px; padding: 14px 18px; flex-wrap: wrap; }
            .oz-stat { display: flex; flex-direction: column; line-height: 1.1; }
            .oz-stat b { font-size: 22px; font-family: var(--mono); }
            .oz-stat small { margin-top: 4px; font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
            .oz-stat.accent b { color: var(--accent); }
            .oz-stat.flow b { color: var(--flow); }
            .oz-stats .oz-live { margin-left: auto; }

            /* ---------- sections ---------- */
            .oz-section { margin-top: 64px; }
            .oz-section-title { margin: 0; font-size: 11px; color: var(--on-scope-muted); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; }
            .oz-section h2 { margin: 6px 0 0; font-size: 24px; font-weight: 700; color: var(--on-scope); }
            .oz-section > .oz-lead { margin-top: 8px; color: var(--on-scope-muted); max-width: 640px; }

            .oz-grid { margin-top: 20px; display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
            .oz-card { padding: 16px 18px; }
            .oz-card-head { display: flex; align-items: center; gap: 8px; }
            .oz-card-head .oz-swatch { width: 10px; height: 10px; border-radius: 3px; background: var(--c, var(--accent)); flex-shrink: 0; }
            .oz-card h3 { margin: 0; font-size: 14px; font-weight: 600; }
            .oz-card p { margin-top: 8px; color: var(--muted); font-size: 13px; }
            .oz-card p b { color: var(--text); font-weight: 600; }
            .oz-card code, .oz-kv dd { font-family: var(--mono); font-size: 12px; }

            .oz-steps { margin: 20px 0 0; padding: 0; list-style: none; display: grid; gap: 10px; }
            .oz-step { display: flex; gap: 14px; padding: 14px 16px; }
            .oz-step-n {
                flex-shrink: 0; width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center;
                font-family: var(--mono); font-weight: 700; font-size: 12px; background: rgba(34, 211, 238, 0.12); color: var(--accent);
            }
            .oz-step p { color: var(--muted); font-size: 13px; }
            .oz-step p b { color: var(--text); font-weight: 600; }

            .oz-kv { display: grid; grid-template-columns: 110px 1fr; gap: 6px 12px; margin: 10px 0 0; }
            .oz-kv dt { color: var(--muted); font-size: 12px; }
            .oz-kv dd { margin: 0; word-break: break-word; }

            .oz-link { color: var(--accent); text-decoration: underline; text-decoration-color: rgba(34, 211, 238, 0.35); text-underline-offset: 3px; }
            .oz-link:hover { text-decoration-color: var(--accent); }
            .oz-lead .oz-link, footer .oz-link { color: var(--on-scope); text-decoration-color: rgba(0, 0, 0, 0.4); }

            footer { margin-top: 80px; border-top: 1px solid rgba(0, 0, 0, 0.25); }
            footer .oz-wrap { padding: 22px 20px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; color: var(--on-scope-muted); font-size: 12px; }

            @media (max-width: 640px) {
                .oz-nav { display: none; }
                .oz-topbar { margin: 12px 12px 0; }
                .oz-stats .oz-live { margin-left: 0; }
            }
        </style>
    </head>
    <body>
        <div class="tag-field" id="tag-field">
            <div class="map-viewport" id="map-viewport">
                <svg class="oz-map" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                    @foreach ($mapSectors as $sector)
                        <path
                            class="{{ $sector['activated'] ? 'oz-sector-active' : 'oz-sector-static' }}"
                            d="{{ $sector['path'] }}"
                            @if ($sector['activated']) style="animation-delay: {{ $sector['activated_index'] * -2.8 }}s" @endif
                        />
                    @endforeach
                </svg>
            </div>
        </div>

        <header class="oz-panel oz-topbar">
            <a href="/" class="oz-brand">Oz<span>Server</span></a>
            <nav class="oz-nav">
                <a href="#features">Features</a>
                <a href="#how-it-works">How it works</a>
                <a href="#for-developers">Developers</a>
            </nav>
            <a href="/live-map" target="_blank" rel="noopener" class="oz-btn on">Live map &rarr;</a>
        </header>

        <main class="oz-wrap">
            <section class="oz-panel oz-hero">
                <span class="oz-badge accent">In testing &middot; built for VATPAC</span>
                <h1>Every vatSys controller, <span>one picture</span> of the airspace.</h1>
                <p>OzServer is a vatSys plugin with a central server behind it. It keeps one shared record of who owns which sector, and every controller's scope follows it - sectors, tags and ATIS included.</p>
                <div class="oz-actions">
                    <a href="/live-map" target="_blank" rel="noopener" class="oz-btn on">Open the live map</a>
                    <a href="#how-it-works" class="oz-btn">How it works</a>
                </div>
            </section>

            <div class="oz-panel oz-stats" id="live-network">
                <div class="oz-stat accent"><b id="stat-oz-controllers">&mdash;</b><small>Controllers on OzServer</small></div>
                <div class="oz-stat"><b id="stat-other-controllers">&mdash;</b><small>Controllers without</small></div>
                <div class="oz-stat flow"><b id="stat-atis">&mdash;</b><small>ATIS on the server</small></div>
                <div class="oz-stat"><b id="stat-in-firs">&mdash;</b><small>Aircraft in our FIRs</small></div>
                <div class="oz-stat accent"><b id="stat-seen">&mdash;</b><small>Seen by OzServer</small></div>
                <div class="oz-live" id="stat-live"><i></i><span>Live network</span></div>
            </div>

            <section id="features" class="oz-section">
                <p class="oz-section-title">Features</p>
                <h2>What the plugin does</h2>
                <div class="oz-grid">
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch"></i><h3>Sector ownership sync</h3></div>
                        <p>Exactly one controller owns a sector at a time. The <b>OzServer Sectors</b> window shows what you own, what's free, and who holds the rest.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch"></i><h3>Claim by transmitting</h3></div>
                        <p>Press Transmit on a VSCS line and the sector is yours. A primary's line brings its group; dropping the line releases it.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch" style="--c:var(--warn)"></i><h3>Sector requests</h3></div>
                        <p>Transmitting on airspace someone else owns asks whether to send them a request. Incoming requests flash, chime, and shade the airspace on your scope.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch"></i><h3>Tags move with the sector</h3></div>
                        <p>When a sector changes hands, the aircraft inside it arrive already under the new owner's jurisdiction. Manual handoffs are unchanged.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch" style="--c:var(--good)"></i><h3>Reconnect recovery</h3></div>
                        <p>A crash holds your sectors for 5 minutes. Reconnect in time and your sectors, VSCS lines and tags come back. A deliberate exit releases them straight away.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch" style="--c:var(--flow)"></i><h3>ATIS uploading</h3></div>
                        <p>vatSys and ATIS Plugin broadcasts are uploaded automatically and shown on the live map. Live network only - never SweatBox.</p>
                    </article>
                </div>
            </section>

            <section id="how-it-works" class="oz-section">
                <p class="oz-section-title">How it works</p>
                <h2>Controlling barely changes</h2>
                <ol class="oz-steps">
                    <li class="oz-panel oz-step"><span class="oz-step-n">1</span><p><b>Log on.</b> Your position's sectors are claimed on OzServer. If its controller is already online, they keep it.</p></li>
                    <li class="oz-panel oz-step"><span class="oz-step-n">2</span><p><b>Transmit to extend.</b> Free airspace is claimed straight away. Owned airspace - fully or in part - asks whether to send its owner a request.</p></li>
                    <li class="oz-panel oz-step"><span class="oz-step-n">3</span><p><b>Hand over.</b> The owner accepts, and the airspace and the aircraft in it move together. Nothing moves without the controller working it agreeing.</p></li>
                    <li class="oz-panel oz-step"><span class="oz-step-n">4</span><p><b>Stay in sync.</b> Every scope follows the same record, and anything that drifts is put right on the next sync - a few seconds at most.</p></li>
                </ol>
            </section>

            <section id="more" class="oz-section">
                <p class="oz-section-title">Also handled</p>
                <h2>The small things</h2>
                <div class="oz-grid">
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><span class="oz-badge flow">FMP</span><h3>Flow positions</h3></div>
                        <p>Non-jurisdiction labels are hidden automatically, and FMP relief cover is marked as such for the controller handing over.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><span class="oz-badge">OBS</span><h3>Observers</h3></div>
                        <p>An observer sees the airspace of the controller they're watching, live, but never claims anything.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><span class="oz-badge good">Live</span><h3>Live and SweatBox apart</h3></div>
                        <p>Live VATSIM, SweatBox-1 and SweatBox-2 are kept completely separate, so training never touches live ownership.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><span class="oz-badge warn">Off</span><h3>Updates and an off switch</h3></div>
                        <p>The plugin updates itself between sessions, and <b>Settings &rarr; Disable OzServer</b> hands everything back to plain vatSys if anything goes wrong.</p>
                    </article>
                </div>
            </section>

            <section id="for-developers" class="oz-section">
                <p class="oz-section-title">For developers</p>
                <h2>Under the hood</h2>
                <p class="oz-lead">Browse the code or follow progress on <a href="https://github.com/JoshuaMicallefYBSU/OzServer-Website" class="oz-link">GitHub</a>.</p>
                <div class="oz-grid">
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch"></i><h3>API</h3></div>
                        <dl class="oz-kv">
                            <dt>Stack</dt><dd>Fastify + TypeScript, PostgreSQL</dd>
                            <dt>Host</dt><dd>api.ozserver.org</dd>
                            <dt>Identity</dt><dd>vatSys session CID + callsign</dd>
                        </dl>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch"></i><h3>Live map</h3></div>
                        <p>The <a href="/live-map" target="_blank" rel="noopener" class="oz-link">live map</a> follows sector, flight and ATIS changes over Server-Sent Events, with a slow poll underneath for anything that can't hold the stream open.</p>
                    </article>
                    <article class="oz-panel oz-card">
                        <div class="oz-card-head"><i class="oz-swatch"></i><h3>Client plugin</h3></div>
                        <p>A vatSys plugin (<code>net472</code>, x86). Ownership is never guessed locally - every action calls the API and re-reads the result.</p>
                    </article>
                </div>
            </section>
        </main>

        <footer>
            <div class="oz-wrap">
                <span>OzServer &mdash; &copy; Joshua Micallef | 2026-{{ date('Y') }}</span>
                <a href="/live-map" target="_blank" rel="noopener" class="oz-link">Live map</a>
            </div>
        </footer>

        <script>
            var apiBaseUrl = @json(rtrim(config('services.ozserver_api.url'), '/'));

            function getJson(path) {
                return fetch(apiBaseUrl + path, { headers: { Accept: 'application/json' } })
                    .then(function (response) {
                        if (!response.ok) throw new Error(path + ': ' + response.status);
                        return response.json();
                    });
            }

            // ---------------- live status strip ----------------
            (function () {
                var el = function (id) { return document.getElementById(id); };
                var liveEl = el('stat-live');

                function show(id, value) { el(id).textContent = value; }

                function loadStats() {
                    // Always the real network, not switchable - this is the public homepage, and
                    // /api/v1/map/* requires ?server= on every request (see map.ts).
                    var controllers = getJson('/api/v1/map/controllers?server=live')
                        .then(function (rows) {
                            // is_ozserver: the controller holds sectors through the plugin.
                            var on = rows.filter(function (c) { return c.is_ozserver; }).length;
                            show('stat-oz-controllers', on);
                            show('stat-other-controllers', rows.length - on);
                        })
                        .catch(function (error) { show('stat-oz-controllers', '?'); show('stat-other-controllers', '?'); throw error; });

                    var atis = getJson('/api/v1/map/atis?server=live')
                        .then(function (rows) { show('stat-atis', rows.length); })
                        .catch(function (error) { show('stat-atis', '?'); throw error; });

                    // Every pilot VATSIM reports inside our airspace, and how many of them a
                    // controller's plugin has a flight record for.
                    var traffic = getJson('/api/v1/map/traffic')
                        .then(function (counts) {
                            show('stat-in-firs', counts.in_firs);
                            show('stat-seen', counts.seen_by_ozserver);
                        })
                        .catch(function (error) { show('stat-in-firs', '?'); show('stat-seen', '?'); throw error; });

                    // The dot goes green only while every count is actually coming back.
                    Promise.all([controllers, atis, traffic])
                        .then(function () { liveEl.classList.add('on'); })
                        .catch(function () { liveEl.classList.remove('on'); });
                }

                loadStats();
                setInterval(loadStats, 15000);
            })();

            // ---------------- background scope: live VATSIM traffic ----------------
            //
            // Every airborne aircraft in VATSIM's datafeed around Australia (GET /map/pilots), placed
            // with the same lon/lat -> 0-100 projection the sector shapes are drawn with (see
            // HomeController::projectX/projectY), labelled the way the Australia dataset's Normal
            // label lays out:
            //
            //   QFA1 H          callsign, wake turbulence category
            //   350 370 45      datafeed level, requested level, groundspeed (first 2 digits)
            //   YSSY            destination
            //
            // Between datafeed refreshes each aircraft is carried along its heading at its
            // groundspeed, so the picture moves rather than jumping every 15 seconds.
            //
            // Labels are laid out around their aircraft so none overlap each other or another
            // aircraft's symbol; one that cannot be placed anywhere is hidden (its symbol stays).
            (function () {
                var viewport = document.getElementById('map-viewport');
                if (!viewport) return;

                var bounds = @json($mapBounds);
                var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                var SVG_NS = 'http://www.w3.org/2000/svg';
                var LEADER = 16;      // px from the symbol's centre to the label's near corner
                var TARGET = 6;       // half the symbol's size, for collision
                var PAD = 2;          // clear space kept around every label

                var leaders = document.createElementNS(SVG_NS, 'svg');
                leaders.setAttribute('class', 'ac-leaders');
                viewport.appendChild(leaders);

                var tracks = new Map(); // callsign -> track

                function pad(value, width) {
                    var text = String(value);
                    while (text.length < width) text = '0' + text;
                    return text;
                }

                // As vatSys shows it: hundreds of feet, A-prefixed below the 10,000 ft transition.
                function formatLevel(feet) {
                    if (!isFinite(feet)) return '---';
                    var hundreds = Math.max(0, Math.round(feet / 100));
                    return (feet < 10500 ? 'A' : '') + pad(hundreds, 3);
                }

                // The filed cruising level arrives however the pilot typed it: "35000", "FL350",
                // "F350", "A090", "9000".
                function formatRfl(raw) {
                    if (!raw) return '---';
                    var text = String(raw).toUpperCase().replace(/\s/g, '');
                    var digits = parseInt(text.replace(/^(FL|F|A)/, ''), 10);
                    if (!isFinite(digits)) return '---';
                    var hundreds = /^(FL|F|A)/.test(text) || digits < 1000 ? digits : Math.round(digits / 100);
                    return pad(hundreds, 3);
                }

                function formatGroundspeed(knots) {
                    return pad(Math.min(99, Math.max(0, Math.round(knots / 10))), 2);
                }

                function labelText(pilot) {
                    return pilot.callsign + (pilot.wake ? ' ' + pilot.wake : '') + '\n'
                        + formatLevel(pilot.altitude) + ' ' + formatRfl(pilot.rfl) + ' ' + formatGroundspeed(pilot.groundspeed) + '\n'
                        + (pilot.arrival || '');
                }

                function createTrack(callsign) {
                    var target = document.createElement('span');
                    target.className = 'tag-target';
                    var label = document.createElement('div');
                    label.className = 'ac-label';
                    var leader = document.createElementNS(SVG_NS, 'line');
                    viewport.appendChild(target);
                    viewport.appendChild(label);
                    leaders.appendChild(leader);
                    return { callsign: callsign, target: target, label: label, leader: leader, text: '', size: null, slot: null, pilot: null, at: 0 };
                }

                function removeTrack(track) {
                    track.target.remove();
                    track.label.remove();
                    track.leader.remove();
                    tracks.delete(track.callsign);
                }

                function loadPilots() {
                    getJson('/api/v1/map/pilots')
                        .then(function (pilots) {
                            var now = Date.now();
                            var seen = new Set();
                            pilots.forEach(function (pilot) {
                                seen.add(pilot.callsign);
                                var track = tracks.get(pilot.callsign) || createTrack(pilot.callsign);
                                tracks.set(pilot.callsign, track);
                                track.pilot = pilot;
                                track.at = now;
                                var text = labelText(pilot);
                                if (text !== track.text) {
                                    track.text = text;
                                    track.label.textContent = text;
                                    track.size = null; // re-measured on the next layout
                                }
                            });
                            tracks.forEach(function (track) { if (!seen.has(track.callsign)) removeTrack(track); });
                            layout();
                        })
                        .catch(function () { /* keep showing the last picture; the next refresh retries */ });
                }

                // Where the aircraft is now, carried on from its last report.
                function currentPosition(track) {
                    var pilot = track.pilot;
                    var seconds = reducedMotion ? 0 : Math.min(60, (Date.now() - track.at) / 1000);
                    var nm = pilot.groundspeed * seconds / 3600;
                    var heading = pilot.heading * Math.PI / 180;
                    var lat = pilot.lat + nm * Math.cos(heading) / 60;
                    var lon = pilot.lon + nm * Math.sin(heading) / (60 * Math.cos(pilot.lat * Math.PI / 180));
                    return {
                        x: (lon - bounds.lonMin) / (bounds.lonMax - bounds.lonMin),
                        y: (bounds.latMax - lat) / (bounds.latMax - bounds.latMin),
                    };
                }

                // Label positions around the symbol, in the order they are tried - up and to the
                // right first, as vatSys places a label by default.
                var SLOTS = ['NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'N'];
                function slotBox(slot, px, py, w, h) {
                    switch (slot) {
                        case 'NE': return { x: px + LEADER * 0.7, y: py - LEADER * 0.7 - h };
                        case 'E':  return { x: px + LEADER, y: py - h / 2 };
                        case 'SE': return { x: px + LEADER * 0.7, y: py + LEADER * 0.7 };
                        case 'S':  return { x: px - w / 2, y: py + LEADER };
                        case 'SW': return { x: px - LEADER * 0.7 - w, y: py + LEADER * 0.7 };
                        case 'W':  return { x: px - LEADER - w, y: py - h / 2 };
                        case 'NW': return { x: px - LEADER * 0.7 - w, y: py - LEADER * 0.7 - h };
                        default:   return { x: px - w / 2, y: py - LEADER - h };
                    }
                }

                function overlaps(a, b) {
                    return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;
                }

                // The point on the label's edge nearest the symbol, where the leader meets it.
                function nearestEdge(box, px, py) {
                    return {
                        x: Math.max(box.x, Math.min(px, box.x + box.w)),
                        y: Math.max(box.y, Math.min(py, box.y + box.h)),
                    };
                }

                function layout() {
                    var width = viewport.clientWidth;
                    var height = viewport.clientHeight;
                    if (!width || !height) return;

                    var visible = [];
                    tracks.forEach(function (track) {
                        if (!track.pilot) return;
                        var p = currentPosition(track);
                        var px = p.x * width, py = p.y * height;
                        var onMap = px >= 0 && px <= width && py >= 0 && py <= height;
                        track.target.style.display = onMap ? '' : 'none';
                        if (!onMap) { track.label.hidden = true; track.leader.style.display = 'none'; return; }
                        track.target.style.left = px + 'px';
                        track.target.style.top = py + 'px';
                        track.px = px;
                        track.py = py;
                        visible.push(track);
                    });

                    // Measured only when the text changed - the label has to be on the page to measure.
                    visible.forEach(function (track) {
                        if (track.size) return;
                        track.label.hidden = false;
                        track.size = { w: track.label.offsetWidth, h: track.label.offsetHeight };
                    });

                    var symbols = visible.map(function (track) {
                        return { owner: track, x: track.px - TARGET, y: track.py - TARGET, w: TARGET * 2, h: TARGET * 2 };
                    });

                    // Labels already on screen are placed first, so a settled picture stays settled
                    // and a newcomer is what gets moved or hidden.
                    visible.sort(function (a, b) {
                        return (b.slot ? 1 : 0) - (a.slot ? 1 : 0) || (a.callsign < b.callsign ? -1 : 1);
                    });

                    var placed = [];
                    visible.forEach(function (track) {
                        var w = track.size.w, h = track.size.h;
                        var order = track.slot ? [track.slot].concat(SLOTS.filter(function (s) { return s !== track.slot; })) : SLOTS;
                        var chosen = null, chosenBox = null;

                        for (var i = 0; i < order.length && !chosen; i++) {
                            var at = slotBox(order[i], track.px, track.py, w, h);
                            var box = { x: at.x - PAD, y: at.y - PAD, w: w + PAD * 2, h: h + PAD * 2 };
                            if (box.x < 0 || box.y < 0 || box.x + box.w > width || box.y + box.h > height) continue;
                            var clear = placed.every(function (other) { return !overlaps(box, other); })
                                && symbols.every(function (symbol) { return symbol.owner === track || !overlaps(box, symbol); });
                            if (clear) { chosen = order[i]; chosenBox = { x: at.x, y: at.y, w: w, h: h }; placed.push(box); }
                        }

                        track.slot = chosen;
                        if (!chosen) {
                            track.label.hidden = true;
                            track.leader.style.display = 'none';
                            return;
                        }

                        track.label.hidden = false;
                        track.label.style.left = chosenBox.x + 'px';
                        track.label.style.top = chosenBox.y + 'px';
                        var end = nearestEdge(chosenBox, track.px, track.py);
                        track.leader.style.display = '';
                        track.leader.setAttribute('x1', track.px);
                        track.leader.setAttribute('y1', track.py);
                        track.leader.setAttribute('x2', end.x);
                        track.leader.setAttribute('y2', end.y);
                    });
                }

                loadPilots();
                setInterval(loadPilots, 15000);
                setInterval(layout, reducedMotion ? 15000 : 1000);
                window.addEventListener('resize', layout);
            })();
        </script>
    </body>
</html>
