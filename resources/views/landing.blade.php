<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OzServer | vatSys Server Management</title>
        <meta name="description" content="OzServer is a central authority server for vatSys, built for the VATPAC Division, keeping sector ownership and tag data consistent across every controller.">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            .tag-field {
                position: fixed;
                inset: 0;
                z-index: -10;
                overflow: hidden;
                pointer-events: none;
            }
            .vatsys-tag {
                position: absolute;
                left: -240px;
                display: flex;
                align-items: center;
                gap: 6px;
                opacity: 0.4;
                animation-name: tag-drift;
                animation-timing-function: linear;
                animation-iteration-count: infinite;
                will-change: transform;
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
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                font-size: 11px;
                line-height: 1.3;
                white-space: nowrap;
            }
            .tag-prefix {
                opacity: 0.75;
            }
            .tag-line2,
            .tag-line3 {
                opacity: 0.85;
            }
            .vatsys-tag { color: #a5f3dc; }
            .vatsys-tag.tag--blue { color: #93c5fd; }
            @keyframes tag-drift {
                0% { transform: translate(0, 0); }
                25% { transform: translate(27vw, -10px); }
                50% { transform: translate(54vw, 8px); }
                75% { transform: translate(81vw, -6px); }
                100% { transform: translate(108vw, 0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .vatsys-tag { animation: none; opacity: 0.15; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <div class="tag-field" id="tag-field"></div>

        <header class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
            <a href="/" class="flex items-center gap-3 text-lg font-semibold tracking-wide text-white">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-400/10 text-cyan-300">O</span>
                <span>OzServer</span>
            </a>
            <a href="#how-it-works" class="text-sm font-medium text-slate-300 transition hover:text-white">
                How it works
            </a>
        </header>

        <main class="mx-auto max-w-5xl px-6 pb-24 pt-12">
            <section class="max-w-2xl">
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-400/25 bg-cyan-400/10 px-3 py-1 text-sm font-medium text-cyan-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-300"></span>
                    In active development &middot; built for the VATPAC Division
                </p>
                <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                    One server, every VATPAC controller in sync.
                </h1>
                <p class="mt-6 text-lg leading-8 text-slate-300">
                    OzServer is a central authority server that every controller's vatSys client connects to, keeping sector ownership and tag data consistent across the whole network — instead of every client deciding for itself.
                </p>
            </section>

            <section id="features" class="mt-20 grid gap-6 sm:grid-cols-2">
                <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <h2 class="text-lg font-semibold text-white">Sector ownership</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300">Exactly one controller owns a sector at a time. Opening a position locks the sectors it covers, and ownership transfers cleanly through a request-and-approve flow.</p>
                </article>
                <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <h2 class="text-lg font-semibold text-white">Tag data authority</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300">The controller holding an aircraft's tag is the only one who can update it. Every other controller sees the same data in real time, read-only, until it changes hands.</p>
                </article>
            </section>

            <section id="how-it-works" class="mt-20">
                <h2 class="text-2xl font-semibold text-white">How it works</h2>
                <ol class="mt-6 space-y-4">
                    <li class="flex gap-4 rounded-2xl border border-white/10 bg-white/5 p-5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-400/10 text-sm font-semibold text-cyan-300">1</span>
                        <p class="text-sm leading-6 text-slate-300"><span class="font-medium text-white">Claim a sector.</span> Controllers pick sectors from a "Manage Airspace Ownership" panel. Unclaimed sectors lock to them immediately.</p>
                    </li>
                    <li class="flex gap-4 rounded-2xl border border-white/10 bg-white/5 p-5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-400/10 text-sm font-semibold text-cyan-300">2</span>
                        <p class="text-sm leading-6 text-slate-300"><span class="font-medium text-white">Request already-owned sectors.</span> If a sector is claimed, the current owner gets a request. If they approve it, ownership — and every tag tied to it — moves over.</p>
                    </li>
                    <li class="flex gap-4 rounded-2xl border border-white/10 bg-white/5 p-5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-400/10 text-sm font-semibold text-cyan-300">3</span>
                        <p class="text-sm leading-6 text-slate-300"><span class="font-medium text-white">Split airspace between adjacent centers.</span> OzServer recalculates sector infill and tag ownership for every affected aircraft automatically.</p>
                    </li>
                    <li class="flex gap-4 rounded-2xl border border-white/10 bg-white/5 p-5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-cyan-400/10 text-sm font-semibold text-cyan-300">4</span>
                        <p class="text-sm leading-6 text-slate-300"><span class="font-medium text-white">Stay in sync.</span> The owning controller writes tag data; everyone else on the network receives it live, read-only.</p>
                    </li>
                </ol>
            </section>

            <section id="live-network" class="mt-20">
                <h2 class="text-2xl font-semibold text-white">Live network</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">Pulled straight from the API that backs the <a href="/ops" class="text-cyan-300 underline decoration-cyan-300/30 underline-offset-4 hover:decoration-cyan-300">live map</a>.</p>
                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <p class="text-sm text-slate-400">Controllers connected</p>
                        <p class="mt-2 text-4xl font-semibold tabular-nums text-white" id="stat-controllers">&mdash;</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <p class="text-sm text-slate-400">Aircraft tags tracked</p>
                        <p class="mt-2 text-4xl font-semibold tabular-nums text-white" id="stat-aircraft">&mdash;</p>
                    </div>
                </div>
            </section>

            <section id="fun-facts" class="mt-20">
                <h2 class="text-2xl font-semibold text-white">A few things OzServer quietly handles</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">Small details controllers benefit from without ever having to think about them.</p>
                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h3 class="text-lg font-semibold text-white">ATIS carries over between controllers</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-300">An airport's ATIS is remembered centrally, not by whoever last typed it. When one controller hands off to another — or logs back in — the current broadcast is just there, instead of needing to be rebuilt from scratch.</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h3 class="text-lg font-semibold text-white">A dropped connection doesn't cost you your sectors</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-300">Crash, or a rough network patch? Reconnecting to the same position within a short grace window hands your sectors and aircraft tags straight back, exactly as you left them.</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h3 class="text-lg font-semibold text-white">Logging in on a position always wins</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-300">If someone's covering your airspace while you're away, logging in under your own position's callsign takes it straight back — no request, no waiting for approval.</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h3 class="text-lg font-semibold text-white">Splitting airspace re-sorts everything for you</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-300">Combine or split adjacent sectors and every aircraft inside them is reassigned to the right controller automatically — nobody has to hand tags across one by one.</p>
                    </article>
                </div>
            </section>

            <section id="for-developers" class="mt-20">
                <h2 class="text-2xl font-semibold text-white">For developers</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    OzServer is open source — browse the code or follow progress on
                    <a href="https://github.com/JoshuaMicallefYBSU/OzServer-Website" class="text-cyan-300 underline decoration-cyan-300/30 underline-offset-4 hover:decoration-cyan-300">GitHub</a>.
                </p>
                <div class="mt-6 grid gap-6 sm:grid-cols-2">
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h3 class="text-lg font-semibold text-white">Backend</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-300">A Laravel application. The vatSys plugin talks to it over a token-authenticated REST API — polling for sector ownership state and pushing flight data record (tag) updates as controllers work traffic.</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h3 class="text-lg font-semibold text-white">Real-time map</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-300">The <a href="/ops" class="text-cyan-300 underline decoration-cyan-300/30 underline-offset-4 hover:decoration-cyan-300">/ops map</a> streams sector, aircraft and ATIS changes over Server-Sent Events, falling back to a 15-second poll for anything that can't hold a long-lived connection.</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h3 class="text-lg font-semibold text-white">Identity</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-300">Controllers are identified by VATSIM CID. Sector ownership and tag authority are both tied to that identity rather than to a client session.</p>
                    </article>
                    <article class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h3 class="text-lg font-semibold text-white">Client plugin</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-300">The vatSys client plugin lives in a separate repository and isn't public yet — this repo covers the server and the website you're on.</p>
                    </article>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/10">
            <div class="mx-auto max-w-5xl px-6 py-8 text-sm text-slate-500">
                OzServer v0.1 &mdash; &copy; Joshua Micallef | 2026-{{ date('Y') }}
            </div>
        </footer>
        <script>
            (function () {
                var apiBaseUrl = @json(rtrim(config('services.ozserver_api.url'), '/'));
                var controllersEl = document.getElementById('stat-controllers');
                var aircraftEl = document.getElementById('stat-aircraft');

                function loadStats() {
                    fetch(apiBaseUrl + '/api/v1/map/controllers', { headers: { Accept: 'application/json' } })
                        .then(function (response) { return response.json(); })
                        .then(function (controllers) { controllersEl.textContent = controllers.length; })
                        .catch(function () { controllersEl.textContent = '?'; });

                    fetch(apiBaseUrl + '/api/v1/map/aircraft', { headers: { Accept: 'application/json' } })
                        .then(function (response) { return response.json(); })
                        .then(function (aircraft) { aircraftEl.textContent = aircraft.length; })
                        .catch(function () { aircraftEl.textContent = '?'; });
                }

                loadStats();
                setInterval(loadStats, 15000);
            })();

            (function () {
                var field = document.getElementById('tag-field');
                if (!field || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

                var callsigns = ['QFA738', 'JST421', 'VOZ912', 'ANZ87', 'TGG306', 'QLK551', 'REX1204', 'SIA221', 'UAE430', 'CPA173', 'FDX58', 'XCJ', 'ETD412', 'RCL11'];
                var wakes = ['L', 'M', 'H'];
                var actypes = ['B738', 'A320', 'A21N', 'B788', 'A332', 'B763', 'B752'];
                var airports = ['YSSY', 'YBBN', 'YBTL', 'YMML', 'YPPH', 'YPAD'];
                var runways = {
                    YSSY: ['16L', '16R', '34L', '34R', '07', '25'],
                    YBBN: ['01L', '01R', '19L', '19R'],
                    YBTL: ['01', '19'],
                    YMML: ['16', '34', '09', '27'],
                    YPPH: ['03', '21', '06', '24'],
                    YPAD: ['05', '23', '12', '30']
                };
                var levels = [280, 310, 330, 350, 370, 390];
                var speeds = [38, 41, 44, 46, 49, 52];
                var symbols = ['>', '^', 'v'];
                var prefixes = ['', '', '', 'C', 'C', 'C01R', 'C02L'];
                var count = 9;

                for (var i = 0; i < count; i++) {
                    var tag = document.createElement('div');
                    tag.className = 'vatsys-tag' + (Math.random() < 0.3 ? ' tag--blue' : '');

                    var duration = 55 + Math.random() * 45;
                    tag.style.top = (Math.random() * 88) + '%';
                    tag.style.animationDuration = duration + 's';
                    tag.style.animationDelay = (-Math.random() * duration) + 's';

                    var cs = callsigns[Math.floor(Math.random() * callsigns.length)];
                    var wake = wakes[Math.floor(Math.random() * wakes.length)];
                    var lvl = levels[Math.floor(Math.random() * levels.length)];
                    var sym = symbols[Math.floor(Math.random() * symbols.length)];
                    var clr = levels[Math.floor(Math.random() * levels.length)];
                    var spd = speeds[Math.floor(Math.random() * speeds.length)];
                    var apt = airports[Math.floor(Math.random() * airports.length)];
                    var act = actypes[Math.floor(Math.random() * actypes.length)];
                    var prefix = prefixes[Math.floor(Math.random() * prefixes.length)];
                    var aptRunways = runways[apt];
                    var rwy = aptRunways[Math.floor(Math.random() * aptRunways.length)];

                    var prefixLine = prefix ? '<div class="tag-prefix">' + prefix + '</div>' : '';
                    var rwyLine = Math.random() < 0.4 ? '<div class="tag-line3">' + rwy + '</div>' : '';

                    tag.innerHTML =
                        '<span class="tag-target"></span>' +
                        '<span class="tag-leader"></span>' +
                        '<div class="tag-box">' +
                            prefixLine +
                            '<div class="tag-line1">' + cs + ' ' + wake + '</div>' +
                            '<div class="tag-line2">' + lvl + sym + clr + ' ' + spd + '</div>' +
                            '<div class="tag-line3">' + apt + ' ' + act + '</div>' +
                            rwyLine +
                        '</div>';

                    field.appendChild(tag);
                }
            })();
        </script>
    </body>
</html>
