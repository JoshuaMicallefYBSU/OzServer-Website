<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OzServer | vatSys Server Management</title>
        <meta name="description" content="OzServer keeps vatSys sector ownership, tags and ATIS in sync across every VATPAC controller.">
        <style>
            /* Same palette and type as the live map (map.blade.php), so the two read as one product. */
            :root {
                --bg: rgba(2, 6, 23, 0.82);
                --line: rgba(255, 255, 255, 0.09);
                --text: #e2e8f0;
                --muted: #94a3b8;
                --faint: #64748b;
                --accent: #22d3ee;
                --warn: #fb923c;
                --bad: #f87171;
                --good: #4ade80;
                --flow: #c4b5fd;
                --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            }
            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; }
            body { margin: 0; min-height: 100vh; background: #020617; color: var(--text); font-family: var(--sans); font-size: 14px; line-height: 1.55; }
            a { color: inherit; }
            p { margin: 0; }

            /* ---------- background: sectors + flying tags (unchanged logic) ---------- */
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
               viewBox. Flying tags are positioned by percentage within this same box (not the
               full window), so they stay aligned with the sector shapes at every window size. */
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
                fill: rgba(34, 211, 238, 0.03);
                stroke: rgba(148, 163, 184, 0.35);
                stroke-width: 0.15;
            }
            .oz-sector-active {
                fill: rgba(34, 211, 238, 0.4);
                stroke: #22d3ee;
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
            .vatsys-tag {
                position: absolute;
                display: flex;
                align-items: center;
                gap: 6px;
                will-change: left, top, opacity;
            }
            .tag-target {
                position: relative;
                flex-shrink: 0;
                width: 13px;
                height: 13px;
                border-radius: 9999px;
                border: 1px solid currentColor;
            }
            .tag-target::before,
            .tag-target::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                background: currentColor;
            }
            .tag-target::before { width: 1px; height: 7px; transform: translate(-50%, -50%); }
            .tag-target::after { width: 7px; height: 1px; transform: translate(-50%, -50%); }
            .tag-leader {
                flex-shrink: 0;
                width: 16px;
                height: 1px;
                background: currentColor;
                opacity: 0.6;
            }
            .tag-box {
                font-family: var(--mono);
                font-size: 11px;
                line-height: 1.3;
                white-space: nowrap;
                background: rgba(30, 41, 59, 0.55);
                border-radius: 3px;
                padding: 1px 4px;
            }
            .tag-line2 {
                opacity: 0.85;
            }
            .vatsys-tag { color: #a5f3dc; }
            .vatsys-tag.tag--blue { color: #93c5fd; }
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
                position: sticky; top: 12px; z-index: 5; margin: 12px auto 0; max-width: 1040px;
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
            .oz-hero { padding: 72px 0 24px; max-width: 680px; }
            .oz-hero h1 { margin: 14px 0 0; font-size: clamp(30px, 5vw, 46px); line-height: 1.1; letter-spacing: -0.01em; font-weight: 700; }
            .oz-hero h1 span { color: var(--accent); }
            .oz-hero p { margin-top: 18px; font-size: 16px; color: var(--muted); max-width: 600px; }
            .oz-hero .oz-actions { margin-top: 26px; display: flex; gap: 10px; flex-wrap: wrap; }
            .oz-hero .oz-btn { padding: 8px 14px; font-size: 13px; }

            /* ---------- live stats strip ---------- */
            .oz-stats { margin-top: 28px; display: flex; align-items: center; gap: 24px; padding: 12px 16px; flex-wrap: wrap; }
            .oz-stat { display: flex; flex-direction: column; line-height: 1.1; }
            .oz-stat b { font-size: 22px; font-family: var(--mono); }
            .oz-stat small { margin-top: 4px; font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
            .oz-stats .oz-live { margin-left: auto; }

            /* ---------- sections ---------- */
            .oz-section { margin-top: 64px; }
            .oz-section-title { margin: 0; font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; }
            .oz-section h2 { margin: 6px 0 0; font-size: 24px; font-weight: 700; }
            .oz-section > .oz-lead { margin-top: 8px; color: var(--muted); max-width: 640px; }

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

            footer { margin-top: 80px; border-top: 1px solid var(--line); }
            footer .oz-wrap { padding: 22px 20px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; color: var(--faint); font-size: 12px; }

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
            <a href="/live-map" class="oz-btn on">Live map &rarr;</a>
        </header>

        <main class="oz-wrap">
            <section class="oz-hero">
                <span class="oz-badge accent">In testing &middot; built for VATPAC</span>
                <h1>Every vatSys controller, <span>one picture</span> of the airspace.</h1>
                <p>OzServer is a vatSys plugin with a central server behind it. It keeps one shared record of who owns which sector, and every controller's scope follows it - sectors, tags and ATIS included.</p>
                <div class="oz-actions">
                    <a href="/live-map" class="oz-btn on">Open the live map</a>
                    <a href="#how-it-works" class="oz-btn">How it works</a>
                </div>

                <div class="oz-panel oz-stats" id="live-network">
                    <div class="oz-stat"><b id="stat-controllers">&mdash;</b><small>Controllers</small></div>
                    <div class="oz-stat"><b id="stat-aircraft">&mdash;</b><small>Aircraft tracked</small></div>
                    <div class="oz-live" id="stat-live"><i></i><span>Live network</span></div>
                </div>
            </section>

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
                        <p>The <a href="/live-map" class="oz-link">live map</a> follows sector, flight and ATIS changes over Server-Sent Events, with a slow poll underneath for anything that can't hold the stream open.</p>
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
                <a href="/live-map" class="oz-link">Live map</a>
            </div>
        </footer>

        <script>
            (function () {
                var apiBaseUrl = @json(rtrim(config('services.ozserver_api.url'), '/'));
                var controllersEl = document.getElementById('stat-controllers');
                var aircraftEl = document.getElementById('stat-aircraft');
                var liveEl = document.getElementById('stat-live');

                function loadStats() {
                    // Always the real network, not switchable - this is the public homepage, and
                    // /api/v1/map/* now requires ?server= on every request (see map.ts).
                    var controllers = fetch(apiBaseUrl + '/api/v1/map/controllers?server=live', { headers: { Accept: 'application/json' } })
                        .then(function (response) { return response.json(); })
                        .then(function (controllers) { controllersEl.textContent = controllers.length; })
                        .catch(function () { controllersEl.textContent = '?'; throw new Error('controllers'); });

                    var aircraft = fetch(apiBaseUrl + '/api/v1/map/aircraft?server=live', { headers: { Accept: 'application/json' } })
                        .then(function (response) { return response.json(); })
                        .then(function (aircraft) { aircraftEl.textContent = aircraft.length; })
                        .catch(function () { aircraftEl.textContent = '?'; throw new Error('aircraft'); });

                    // The dot goes green only while both counts are actually coming back.
                    Promise.all([controllers, aircraft])
                        .then(function () { liveEl.classList.add('on'); })
                        .catch(function () { liveEl.classList.remove('on'); });
                }

                loadStats();
                setInterval(loadStats, 15000);
            })();

            (function () {
                var field = document.getElementById('map-viewport');
                if (!field || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                if (!field.animate) return;

                // Positions come from the server, projected onto the map's 0-100 viewBox with
                // the exact same lon/lat -> x/y formula used to draw the sector polygons above
                // (see HomeController::projectX/projectY), so tags actually track over their
                // real airport rather than just somewhere on screen.
                var airports = @json($mapAirports);
                var airlinePrefixes = ['QFA', 'JST', 'VOZ', 'ANZ', 'TGG', 'QLK', 'REX', 'NAU', 'CPA', 'SIA'];
                var tagCount = 5 + Math.floor(Math.random() * 46); // 5-50

                // Each tag keeps its callsign for its whole (endless) lifetime, so drawing
                // from this shared set on creation is enough to guarantee no two tags ever
                // show the same callsign at once.
                var usedCallsigns = new Set();

                function nextCallsign() {
                    var callsign;
                    do {
                        var prefix = airlinePrefixes[Math.floor(Math.random() * airlinePrefixes.length)];
                        callsign = prefix + (1 + Math.floor(Math.random() * 999));
                    } while (usedCallsigns.has(callsign));
                    usedCallsigns.add(callsign);
                    return callsign;
                }

                function lerp(a, b, t) { return a + (b - a) * t; }

                function randomAirportPair() {
                    var from = airports[Math.floor(Math.random() * airports.length)];
                    var to;
                    do {
                        to = airports[Math.floor(Math.random() * airports.length)];
                    } while (to === from);
                    return [from, to];
                }

                function flyTag(el, routeEl) {
                    var pair = randomAirportPair();
                    var from = pair[0];
                    var to = pair[1];
                    routeEl.textContent = from.code + '→' + to.code;

                    // Fades in/out over the first and last 8% of the flight so tags never
                    // just pop into existence mid-air or vanish on arrival.
                    var fades = [0, 0.08, 0.92, 1];
                    var keyframes = fades.map(function (t) {
                        return {
                            left: lerp(from.x, to.x, t) + '%',
                            top: lerp(from.y, to.y, t) + '%',
                            opacity: (t === 0 || t === 1) ? 0 : 0.85,
                            offset: t,
                        };
                    });

                    var duration = 26000 + Math.random() * 20000;
                    var anim = el.animate(keyframes, { duration: duration, easing: 'linear' });
                    anim.onfinish = function () { flyTag(el, routeEl); };
                }

                for (var i = 0; i < tagCount; i++) {
                    (function () {
                        var tag = document.createElement('div');
                        tag.className = 'vatsys-tag' + (Math.random() < 0.3 ? ' tag--blue' : '');
                        tag.innerHTML =
                            '<span class="tag-target"></span>' +
                            '<span class="tag-leader"></span>' +
                            '<div class="tag-box">' +
                                '<div class="tag-line1">' + nextCallsign() + '</div>' +
                                '<div class="tag-line2" data-route></div>' +
                            '</div>';
                        field.appendChild(tag);

                        var routeEl = tag.querySelector('[data-route]');

                        setTimeout(function () { flyTag(tag, routeEl); }, Math.random() * 4000);
                    })();
                }
            })();
        </script>
    </body>
</html>
