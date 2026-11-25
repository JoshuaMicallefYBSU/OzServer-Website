<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OzServer | vatSys Server Management</title>
        <meta name="description" content="OzServer keeps vatSys sector ownership, tags and ATIS in sync across every VATPAC controller.">
        <meta property="og:title" content="OzServer">
        <meta property="og:description" content="One live record of who is working which sector across VATSIM Australia.">
        @if ($social = \App\Support\PublicUrl::file('images/logo/social.png'))
        <meta property="og:image" content="{{ url($social) }}">
        <meta name="twitter:card" content="summary_large_image">
        @endif
        @include('partials.icons')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
        <style>
            /* One full-bleed live chart of Australian airspace carries the page. Everything after it
               is quiet, single-column bands on a 1180px measure, with the plugin's own windows on
               one dark stage. */
            :root {
                /* The chart, the stage and the install band are the scope at night in both themes. */
                --night: #060A13;
                --land: #111A2D;
                --coast: #41557F;
                --grid: rgba(150, 172, 225, .11);
                --ice: #EEF2FB;
                --ice-dim: #9AA6C2;
                --plugin: #5B8CFF;      /* a sector held by a controller on OzServer */
                --external: #E6BE7C;    /* a sector held by a controller without the plugin */

                --bg: #F7F8FA;
                --fg: #0A0F1C;
                --muted: #5A6377;
                --rule: #DCE0E8;
                --accent: #2450E6;

                --sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
                --mono: "Geist Mono", ui-monospace, "Cascadia Mono", Consolas, monospace;
                --measure: 1180px;
            }
            @media (prefers-color-scheme: dark) {
                :root { --bg: #0A0E18; --fg: #EEF2FB; --muted: #96A1BA; --rule: #1E273A; --accent: #86A6FF; color-scheme: dark; }
            }

            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; }
            body { margin: 0; background: var(--bg); color: var(--fg); font: 400 17px/1.55 var(--sans); -webkit-font-smoothing: antialiased; }
            a { color: inherit; }
            img { max-width: 100%; }
            [hidden] { display: none !important; }
            h1, h2, h3, p, ol, ul, dl, figure { margin: 0; }
            h1, h2, h3 { text-wrap: balance; font-weight: 600; }
            :focus-visible { outline: 2px solid var(--plugin); outline-offset: 3px; border-radius: 4px; }
            .wrap { max-width: var(--measure); margin: 0 auto; padding-left: max(24px, env(safe-area-inset-left, 0px)); padding-right: max(24px, env(safe-area-inset-right, 0px)); }

            .btn { display: inline-flex; align-items: center; height: 46px; padding: 0 20px; border-radius: 10px; font: 500 15.5px/1 var(--sans); text-decoration: none; white-space: nowrap; transition: transform .15s ease, background-color .15s ease; }
            .btn:hover { transform: translateY(-1px); }
            .btn-light { background: var(--ice); color: #070B14; }
            .btn-ghost { color: var(--ice); box-shadow: inset 0 0 0 1px rgba(238, 242, 251, .28); }
            .btn-ghost:hover { background: rgba(238, 242, 251, .08); }

            /* ---------- hero ---------- */
            .hero { position: relative; background: var(--night); color: var(--ice); isolation: isolate; overflow: hidden; }
            .hero-top { position: relative; overflow: hidden; }
            .chart { position: absolute; inset: 0; width: 100%; height: 100%; display: block; }
            .chart .ocean { fill: var(--night); }
            .chart .graticule { stroke: rgba(150, 172, 225, .055); stroke-width: 1; fill: none; }
            .chart .land { fill: var(--land); stroke: var(--coast); stroke-width: .9; stroke-linejoin: round; }
            .chart .sector { fill: none; stroke: var(--grid); stroke-width: .8; }
            .chart .held { stroke-width: .8; stroke-linejoin: round; }
            .chart .held.plugin { fill: rgba(91, 140, 255, .2); stroke: rgba(132, 168, 255, .5); }
            .chart .held.external { fill: rgba(230, 190, 124, .13); stroke: rgba(230, 190, 124, .38); }
            .chart .ac .sym, .chart .ac .hist circle { fill: var(--ice); }
            .chart .ac .hit { fill: transparent; cursor: pointer; }
            .chart .ac .ring { fill: none; stroke: transparent; stroke-width: 1; }
            .chart .ac.on .sym { fill: #fff; }
            .chart .ac.on .ring { stroke: var(--ice); }

            .scrim { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(6, 10, 19, .92) 0%, rgba(6, 10, 19, .7) 30%, rgba(6, 10, 19, 0) 50%); pointer-events: none; }
            .hero-inner { position: relative; display: flex; flex-direction: column; min-height: clamp(600px, 76vh, 800px); pointer-events: none; }
            .hero-inner > * { pointer-events: auto; }

            .nav { display: flex; align-items: center; gap: 32px; height: 72px; }
            .brand { display: inline-flex; align-items: center; gap: 10px; font: 600 18px/1 var(--sans); letter-spacing: -.01em; text-decoration: none; }
            .brand svg { width: 24px; height: 24px; }
            .nav-links { display: flex; gap: 28px; margin-left: auto; font-size: 15px; }
            .nav-links a { color: var(--ice-dim); text-decoration: none; transition: color .15s; }
            .nav-links a:hover { color: var(--ice); }
            .nav .btn { height: 38px; padding: 0 16px; font-size: 14.5px; }

            .pitch { margin-top: auto; margin-bottom: auto; padding-block: 40px; max-width: 520px; pointer-events: none; }
            .pitch > * { pointer-events: auto; }
            .pitch h1 { font-size: clamp(42px, 5.3vw, 74px); line-height: 1; letter-spacing: -.038em; }
            .pitch p { margin-top: 22px; font-size: 18.5px; line-height: 1.5; color: #C3CBDD; max-width: 30em; }
            .cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }

            .rail { border-top: 1px solid rgba(150, 172, 225, .16); }
            .rail-inner { display: flex; flex-wrap: wrap; align-items: center; gap: 14px 44px; padding-block: 18px; }
            .live { display: inline-flex; align-items: center; gap: 9px; font: 500 13.5px/1 var(--mono); color: var(--ice-dim); font-variant-numeric: tabular-nums; }
            .live i { width: 8px; height: 8px; border-radius: 50%; background: #5A6377; }
            .live.on { color: var(--ice); }
            .live.on i { background: #56D69A; animation: beat 2.4s ease-out infinite; }
            @keyframes beat { 0% { box-shadow: 0 0 0 0 rgba(86, 214, 154, .6); } 70% { box-shadow: 0 0 0 9px rgba(86, 214, 154, 0); } 100% { box-shadow: 0 0 0 0 rgba(86, 214, 154, 0); } }
            .fig { display: flex; align-items: baseline; gap: 9px; font-size: 14.5px; color: var(--ice-dim); }
            .fig b { font: 500 22px/1 var(--sans); letter-spacing: -.02em; color: var(--ice); font-variant-numeric: tabular-nums; }
            .legend { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-left: auto; font-size: 13.5px; color: var(--ice-dim); }
            .legend span { display: inline-flex; align-items: center; gap: 8px; }
            .legend i { width: 14px; height: 10px; border-radius: 2px; }
            .legend .k-plugin { background: rgba(91, 140, 255, .3); box-shadow: inset 0 0 0 1px rgba(132, 168, 255, .8); }
            .legend .k-external { background: rgba(230, 190, 124, .2); box-shadow: inset 0 0 0 1px rgba(230, 190, 124, .7); }

            .chip, .tip { position: absolute; left: 0; top: 0; font: 500 12.5px/1.35 var(--mono); white-space: nowrap; pointer-events: none; }
            .chip { padding: 5px 9px; border-radius: 6px; background: rgba(6, 10, 19, .78); color: var(--ice); box-shadow: inset 0 0 0 1px rgba(150, 172, 225, .22); }
            .chip small { font-size: inherit; color: var(--ice-dim); }
            .chip.external { box-shadow: inset 0 0 0 1px rgba(230, 190, 124, .55); }
            .chip.plugin { box-shadow: inset 0 0 0 1px rgba(132, 168, 255, .7); }
            .tip { padding: 8px 11px; border-radius: 8px; background: #EEF2FB; color: #070B14; box-shadow: 0 10px 30px rgba(0, 0, 0, .45); opacity: 0; transition: opacity .12s; font-variant-numeric: tabular-nums; }
            .tip.show { opacity: 1; }
            .tip b { font-weight: 500; }
            .tip span { color: #4B556B; }

            /* ---------- bands ---------- */
            .band { padding-block: clamp(72px, 9vw, 128px); }
            .band + .band { border-top: 1px solid var(--rule); }
            h2 { font-size: clamp(30px, 3.5vw, 46px); line-height: 1.06; letter-spacing: -.03em; }
            .lede { margin-top: 18px; color: var(--muted); max-width: 34em; }

            .split { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); gap: clamp(32px, 6vw, 96px); align-items: start; }
            .split > * { min-width: 0; }
            .points { list-style: none; padding: 0; display: grid; }
            .points li { padding-block: 22px; border-top: 1px solid var(--rule); display: grid; gap: 6px; }
            .points li:last-child { border-bottom: 1px solid var(--rule); }
            .points h3 { font-size: 18.5px; letter-spacing: -.012em; }
            .points p { color: var(--muted); max-width: 36em; }

            /* the plugin's windows */
            .tabs { display: flex; gap: 6px 30px; margin-top: 40px; border-bottom: 1px solid var(--rule); overflow-x: auto; scrollbar-width: none; }
            .tabs::-webkit-scrollbar { display: none; }
            .tabs button { appearance: none; background: none; border: 0; padding: 0 0 14px; margin-bottom: -1px; font: 500 15.5px/1.2 var(--sans); color: var(--muted); border-bottom: 2px solid transparent; cursor: pointer; white-space: nowrap; transition: color .15s; }
            .tabs button:hover { color: var(--fg); }
            .tabs button[aria-selected="true"] { color: var(--fg); border-bottom-color: var(--fg); }
            .about { margin-top: 28px; min-height: 5.2em; max-width: 38em; }
            .about h3 { font-size: 20px; letter-spacing: -.015em; }
            .about p { margin-top: 8px; color: var(--muted); }
            .stage { margin-top: 28px; background: var(--night); border-radius: 18px; box-shadow: inset 0 0 0 1px rgba(150, 172, 225, .14); display: grid; place-items: center; min-height: 560px; padding: clamp(18px, 4vw, 48px); overflow: hidden; position: relative; }
            .stage img { display: block; max-width: 100%; height: auto; box-shadow: 0 30px 80px rgba(0, 0, 0, .6), 0 4px 14px rgba(0, 0, 0, .5); }
            .stage figcaption { position: absolute; left: 20px; bottom: 16px; font: 400 12.5px/1 var(--mono); color: #6E7A96; }

            /* a session */
            .steps { list-style: none; padding: 0; margin-top: 48px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 clamp(24px, 4vw, 56px); counter-reset: step; }
            .steps li { counter-increment: step; padding-top: 22px; border-top: 1px solid var(--fg); display: grid; gap: 8px; align-content: start; }
            .steps li::before { content: counter(step); font: 500 14px/1 var(--mono); color: var(--muted); }
            .steps h3 { font-size: 21px; letter-spacing: -.018em; margin-top: 10px; }
            .steps p { color: var(--muted); }

            /* install */
            .install { background: var(--night); color: var(--ice); }
            .install .wrap { padding-block: clamp(72px, 9vw, 120px); }
            .install .lede { color: #B4BDD2; }
            .install ol { margin-top: 26px; padding: 0; list-style: none; counter-reset: i; display: grid; gap: 12px; color: #D5DBEA; }
            .install ol li { counter-increment: i; display: grid; grid-template-columns: 28px minmax(0, 1fr); align-items: baseline; }
            .install ol li::before { content: counter(i); font: 500 13.5px/1 var(--mono); color: var(--ice-dim); }
            .install code { font: 500 .88em/1 var(--mono); color: var(--ice); background: rgba(150, 172, 225, .12); padding: 3px 7px; border-radius: 5px; overflow-wrap: anywhere; }
            .release { border-radius: 16px; box-shadow: inset 0 0 0 1px rgba(150, 172, 225, .18); padding: 28px; display: grid; gap: 18px; background: rgba(150, 172, 225, .04); }
            .release dl { display: grid; }
            .release dl div { display: flex; justify-content: space-between; gap: 16px; padding-block: 11px; border-top: 1px solid rgba(150, 172, 225, .14); font-size: 15px; }
            .release dl div:last-child { padding-bottom: 0; }
            .release dl div.first { border-top: 0; padding-top: 0; }
            .release dt { color: var(--ice-dim); }
            .release dd { margin: 0; font: 500 14.5px/1.4 var(--mono); text-align: right; }
            .release .cta { margin-top: 4px; }

            footer { padding-block: 34px; font-size: 14px; color: var(--muted); }
            footer .wrap { display: flex; flex-wrap: wrap; gap: 10px 28px; align-items: center; }
            .foot-brand { display: inline-flex; align-items: center; gap: 9px; color: var(--fg); }
            .foot-brand svg { width: 18px; height: 18px; flex: none; }
            footer a { text-decoration: none; }
            footer a:hover { color: var(--fg); }
            footer .note { margin-left: auto; }

            @media (max-width: 900px) {
                .nav-links { display: none; }
                .nav .btn { margin-left: auto; }
                .hero-top { display: flex; flex-direction: column; }
                .hero-inner { min-height: 0; order: 1; }
                .chart { position: relative; inset: auto; height: auto; aspect-ratio: 850 / 760; order: 2; }
                .scrim { display: none; }
                .pitch { padding-block: 28px 36px; max-width: none; }
                .legend { margin-left: 0; }
                .split { grid-template-columns: 1fr; }
                .steps { grid-template-columns: 1fr; gap: 32px; }
                .stage { min-height: 0; gap: 14px; justify-items: start; }
                .stage figcaption { position: static; }
                footer .note { margin-left: 0; }
            }
            @media (prefers-reduced-motion: reduce) {
                html { scroll-behavior: auto; }
                .live.on i { animation: none; }
                .btn { transition: none; }
            }
        </style>
    </head>
    <body>
        <header class="hero" id="top">
            <div class="hero-top">
                <svg class="chart" id="chart" viewBox="431 30 950 840" preserveAspectRatio="xMaxYMid meet" role="img" aria-label="Chart of Australian airspace showing staffed sectors and aircraft">
                    <rect class="ocean" x="-400" y="-400" width="2400" height="1800"/>
                    {{-- A line every ten degrees: 90 E to 160 E, 10 S to 40 S. --}}
                    <path class="graticule" d="@foreach (range(90, 160, 10) as $lon)M{{ round(($lon - $projection['lon0']) * $projection['c'] * $projection['k'], 1) }} -200V1200 @endforeach @foreach (range(-10, -40, 10) as $lat)M-400 {{ round(($projection['lat0'] - $lat) * $projection['k'], 1) }}H2000 @endforeach"/>
                    <path class="land" d="{{ $coastPath }}"/>
                    <g>
                        @foreach ($sectorPaths as $path)
                        <path class="sector" d="{{ $path }}"/>
                        @endforeach
                    </g>
                    <g id="held"></g>
                    <g id="traffic"></g>
                </svg>
                <div class="scrim"></div>

                <div class="hero-inner wrap">
                    <nav class="nav" aria-label="Main">
                        <a class="brand" href="/">
                            <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M5.43 22.86A12.6 12.6 0 0 1 22.86 5.43" fill="none" stroke="#EEF2FB" stroke-width="2.8" stroke-linecap="round"/><path d="M26.57 9.14A12.6 12.6 0 0 1 9.14 26.57" fill="none" stroke="#5B8CFF" stroke-width="2.8" stroke-linecap="round"/><path transform="translate(16 16) scale(0.56)" d="M0-4C0-4 12-12 4 0C4 0 12 12 0 4C0 4-12 12-4 0C-4 0-12-12 0-4Z" fill="#EEF2FB"/></svg>
                            OzServer
                        </a>
                        <div class="nav-links">
                            <a href="/live-map">Live map</a>
                            <a href="/how-to">How-To</a>
                            <a href="#windows">In vatSys</a>
                        </div>
                        <a class="btn btn-light" href="#install">Install</a>
                    </nav>

                    <div class="pitch">
                        <h1>Every sector, accounted for.</h1>
                        <p>OzServer keeps one live record of who is working which sector across VATSIM Australia. Sectors, aircraft and arrival sequences move between controllers inside vatSys.</p>
                        <div class="cta">
                            <a class="btn btn-light" href="#install">Install the plugin</a>
                            <a class="btn btn-ghost" href="/live-map">Open the live map</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rail">
                <div class="rail-inner wrap">
                    <span class="live" id="live"><i></i><span id="clock">Connecting</span></span>
                    <span class="fig"><b id="n-controllers">&ndash;</b> <span id="l-controllers">controllers online</span></span>
                    <span class="fig"><b id="n-sectors">&ndash;</b> sectors staffed</span>
                    <span class="fig"><b id="n-aircraft">&ndash;</b> aircraft in Australian airspace</span>
                    <span class="legend">
                        <span><i class="k-plugin"></i>On OzServer</span>
                        <span><i class="k-external"></i>Without the plugin</span>
                    </span>
                </div>
            </div>
            <div id="chips"></div>
            <div class="tip" id="tip" role="status"></div>
        </header>

        <main>
            <section class="band">
                <div class="wrap split">
                    <div>
                        <h2>One sector, one controller, and everyone's vatSys agrees who.</h2>
                        <p class="lede">The server keeps every controller in step. There is nothing to sync and nothing to set up after the plugin is installed.</p>
                    </div>
                    <ul class="points">
                        <li><h3>Take airspace the way you already control</h3><p>Log on, or press Transmit on a VSCS line. If nobody holds the sector, it is yours straight away.</p></li>
                        <li><h3>Ask for airspace someone else holds</h3><p>A request goes to the controller working it. Nothing changes hands until they accept.</p></li>
                        <li><h3>Aircraft go with the airspace</h3><p>When a sector changes hands, the aircraft its old owner was working inside it are handed to the new owner automatically.</p></li>
                    </ul>
                </div>
            </section>

            @if (count($windows) > 0)
            <section class="band" id="windows">
                <div class="wrap">
                    <h2>It lives inside vatSys.</h2>
                    <p class="lede">These are the plugin's own windows, drawn by vatSys with the Australia profile's colours.</p>

                    <div class="tabs" role="tablist" aria-label="Plugin windows">
                        @foreach ($windows as $index => $window)
                        <button type="button" role="tab" id="tab-{{ $index }}" aria-controls="stage" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? '0' : '-1' }}">{{ $window['tab'] }}</button>
                        @endforeach
                    </div>
                    <div class="about" id="about" aria-live="polite">
                        <h3>{{ $windows[0]['title'] }}</h3>
                        <p>{{ $windows[0]['text'] }}</p>
                    </div>
                    <figure class="stage" id="stage" role="tabpanel">
                        <img id="shot" src="{{ $windows[0]['src'] }}" alt="{{ $windows[0]['alt'] }}" width="{{ $windows[0]['w'] }}" height="{{ $windows[0]['h'] }}">
                        <figcaption id="shot-name">{{ $windows[0]['caption'] }}</figcaption>
                    </figure>
                </div>
            </section>
            @endif

            <section class="band">
                <div class="wrap">
                    <h2>A session, start to finish.</h2>
                    <ol class="steps">
                        <li><h3>Log on</h3><p>Connect as you normally would. Your position's sectors become yours. If someone is covering them, they get one request for the lot.</p></li>
                        <li><h3>Extend by transmitting</h3><p>A sub-sector's line claims that sector. A primary's line claims its whole group. Switching a line to Idle gives the sector back.</p></li>
                        <li><h3>Log off</h3><p>Your sectors are released. If your connection drops instead, they are kept for you for two minutes so you can come straight back.</p></li>
                    </ol>
                </div>
            </section>
        </main>

        <section class="install" id="install">
            <div class="wrap split">
                <div>
                    <h2>Install it once.</h2>
                    <p class="lede">You need vatSys with an Australian or Pacific profile. After that the plugin keeps itself up to date.</p>
                    <ol>
                        <li>Close vatSys.</li>
                        <li><span>In the vatSys Launcher, open Plugins and choose <code>JoshuaMicallefYBSU/OzServer-Plugin</code>.</span></li>
                        <li>Pick your profile and press Install.</li>
                        <li>Start vatSys and connect.</li>
                    </ol>
                </div>
                <div class="release">
                    <dl>
                        {{-- Filled from the API's /plugin/release; left out while that has not answered. --}}
                        <div id="release-version" hidden><dt>Latest release</dt><dd></dd></div>
                        <div id="release-date" hidden><dt>Released</dt><dd></dd></div>
                        <div class="first" id="release-fits"><dt>Works with</dt><dd>vatSys, Australia and Pacific profiles</dd></div>
                    </dl>
                    <div class="cta">
                        <a class="btn btn-light" href="https://github.com/JoshuaMicallefYBSU/OzServer-Plugin/releases/latest">Download the zip</a>
                        <a class="btn btn-ghost" href="/how-to">Read the How-To</a>
                    </div>
                </div>
            </div>
        </section>

        <footer>
            <div class="wrap">
                <span class="foot-brand">
                    <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M5.43 22.86A12.6 12.6 0 0 1 22.86 5.43" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/><path d="M26.57 9.14A12.6 12.6 0 0 1 9.14 26.57" fill="none" stroke="var(--accent)" stroke-width="2.8" stroke-linecap="round"/><path transform="translate(16 16) scale(0.56)" d="M0-4C0-4 12-12 4 0C4 0 12 12 0 4C0 4-12 12-4 0C-4 0-12-12 0-4Z" fill="currentColor"/></svg>
                    OzServer &mdash; &copy; Joshua Micallef | 2026{{ date('Y') > 2026 ? '-'.date('Y') : '' }}
                </span>
                <a href="/live-map">Live map</a>
                <a href="/how-to">How-To</a>
                <a href="https://github.com/JoshuaMicallefYBSU/OzServer-Website">GitHub</a>
                <span class="note">For flight simulation only.</span>
            </div>
        </footer>

        <script>
            const API = @json(rtrim(config('services.ozserver_api.url'), '/'));
            const P = @json($projection);
            const SHOTS = @json($windows);

            const px = lon => (lon - P.lon0) * P.c * P.k, py = lat => (P.lat0 - lat) * P.k;
            const r1 = value => Math.round(value * 10) / 10;
            const el = id => document.getElementById(id);

            function getJson(path) {
                return fetch(API + path, { headers: { Accept: 'application/json' } }).then(response => {
                    if (!response.ok) throw new Error(path + ': ' + response.status);
                    return response.json();
                });
            }

            // ---------------- the chart: who holds what, and the traffic ----------------
            //
            // Always the real network: this is the public homepage, and /api/v1/map/* takes ?server=
            // on every request. Read every 15 seconds. Between reads each aircraft is carried along
            // its heading at its groundspeed, so the picture moves rather than jumping.
            (function () {
                const svg = el('chart'), hero = document.querySelector('.hero');
                const held = el('held'), traffic = el('traffic'), chips = el('chips'), tip = el('tip');
                const NS = 'http://www.w3.org/2000/svg';
                const narrow = matchMedia('(max-width: 900px)');
                const still = matchMedia('(prefers-reduced-motion: reduce)');
                const aircraft = new Map();   // callsign -> { p, g, at }
                let anchors = [];             // one per chip: where on the chart it sits

                function node(name, attrs) {
                    const made = document.createElementNS(NS, name);
                    for (const key in attrs) made.setAttribute(key, attrs[key]);
                    return made;
                }

                // One glyph per aircraft: a dart on its heading, with the trail of where it has just been.
                function glyph(callsign) {
                    const g = node('g', { class: 'ac' });
                    const trail = node('g', { class: 'hist' });
                    for (let i = 1; i <= 4; i++) trail.appendChild(node('circle', { cx: 0, cy: 4.2 * i + 3, r: 0.9, opacity: 0.55 - i * 0.11 }));
                    g.append(trail, node('circle', { class: 'ring', r: 9 }), node('path', { class: 'sym', d: 'M0-5.2L3.6 4.2L0 2.2L-3.6 4.2Z' }), node('circle', { class: 'hit', r: 11 }));
                    g.addEventListener('pointerenter', () => show(callsign));
                    g.addEventListener('pointerleave', hide);
                    g.addEventListener('click', event => { event.stopPropagation(); show(callsign); });
                    traffic.appendChild(g);
                    return g;
                }

                function place() {
                    const ctm = svg.getScreenCTM();
                    if (!ctm) return;
                    const scale = Math.max(1.15, 1 / ctm.a), now = Date.now();
                    for (const entry of aircraft.values()) {
                        // Dead reckoning: one minute of latitude is one nautical mile.
                        const p = entry.p, hours = still.matches ? 0 : (now - entry.at) / 3600000;
                        const rad = p.heading * Math.PI / 180, nm = p.groundspeed * hours;
                        const lat = p.lat + nm * Math.cos(rad) / 60;
                        const lon = p.lon + nm * Math.sin(rad) / (60 * Math.cos(lat * Math.PI / 180));
                        entry.x = px(lon); entry.y = py(lat);
                        entry.g.setAttribute('transform', 'translate(' + entry.x.toFixed(1) + ' ' + entry.y.toFixed(1) + ') rotate(' + p.heading + ') scale(' + scale.toFixed(2) + ')');
                    }
                    const box = hero.getBoundingClientRect();
                    [...chips.children].forEach((chip, i) => {
                        const at = new DOMPoint(anchors[i].x, anchors[i].y).matrixTransform(ctm);
                        const left = Math.min(Math.max(8, at.x - box.left - chip.offsetWidth / 2), box.width - chip.offsetWidth - 8);
                        chip.style.transform = 'translate(' + Math.round(left) + 'px, ' + Math.round(at.y - box.top - chip.offsetHeight / 2) + 'px)';
                    });
                }

                let shown = null;
                function show(callsign) {
                    const entry = aircraft.get(callsign);
                    if (!entry) return;
                    hide();
                    shown = entry.g;
                    shown.classList.add('on');
                    const p = entry.p;
                    // As vatSys shows a level: hundreds of feet, an altitude below the 10,000 ft transition.
                    const level = p.altitude < 10500 ? 'A' + String(Math.max(0, Math.round(p.altitude / 100))).padStart(3, '0') : 'FL' + Math.round(p.altitude / 100);
                    tip.replaceChildren();
                    const name = document.createElement('b');
                    name.textContent = p.callsign;
                    tip.append(name);
                    if (p.wake) { const wake = document.createElement('span'); wake.textContent = ' ' + p.wake; tip.append(wake); }
                    tip.append(document.createElement('br'), level + ' ');
                    const at = document.createElement('span'); at.textContent = 'at'; tip.append(at, ' ' + p.groundspeed + ' kt');
                    if (p.arrival) { const to = document.createElement('span'); to.textContent = 'to'; tip.append(document.createElement('br'), to, ' ' + p.arrival); }

                    const ctm = svg.getScreenCTM(), box = hero.getBoundingClientRect();
                    const point = new DOMPoint(entry.x, entry.y).matrixTransform(ctm);
                    const left = Math.min(Math.max(8, point.x - box.left + 14), box.width - tip.offsetWidth - 8);
                    tip.style.transform = 'translate(' + Math.round(left) + 'px, ' + Math.round(point.y - box.top - tip.offsetHeight - 12) + 'px)';
                    tip.classList.add('show');
                }
                function hide() {
                    if (shown) shown.classList.remove('on');
                    shown = null;
                    tip.classList.remove('show');
                }
                document.addEventListener('click', hide);

                function rings(sector) {
                    return (sector.boundary || []).filter(ring => Array.isArray(ring) && ring.length > 2)
                        .map(ring => ring.map(point => [px(point.lon), py(point.lat)]));
                }

                // The staffed sectors, shaded by whether their controller is on OzServer, and one
                // label per controller on the sector they are logged on as.
                function drawSectors(sectors) {
                    const owned = sectors.filter(sector => sector.owner && sector.owner.callsign);
                    held.replaceChildren(...owned.map(sector => node('path', {
                        class: 'held ' + (sector.external ? 'external' : 'plugin'),
                        d: rings(sector).map(ring => ring.map((point, i) => (i ? 'L' : 'M') + r1(point[0]) + ' ' + r1(point[1])).join('') + 'Z').join(''),
                    })));

                    const owners = new Map();
                    for (const sector of owned) {
                        const owner = owners.get(sector.owner.callsign) || { callsign: sector.owner.callsign, external: !!sector.external, sectors: [] };
                        owner.sectors.push(sector);
                        owners.set(owner.callsign, owner);
                    }

                    anchors = [];
                    chips.replaceChildren();
                    for (const owner of owners.values()) {
                        const own = owner.sectors.find(sector => sector.callsign === owner.callsign) || owner.sectors[0];
                        const points = rings(own).flat();
                        if (points.length === 0) continue;
                        anchors.push({
                            x: points.reduce((sum, point) => sum + point[0], 0) / points.length,
                            y: points.reduce((sum, point) => sum + point[1], 0) / points.length,
                        });
                        const chip = document.createElement('div');
                        chip.className = 'chip ' + (owner.external ? 'external' : 'plugin');
                        chip.textContent = owner.callsign + ' ';
                        const count = document.createElement('small');
                        count.textContent = owner.sectors.length + (owner.sectors.length === 1 ? ' sector' : ' sectors');
                        chip.append(count);
                        chips.append(chip);
                    }
                    return owned.length;
                }

                function drawTraffic(pilots) {
                    const seen = new Set(), now = Date.now();
                    for (const p of pilots) {
                        // Airborne, and on the chart.
                        if (!(p.groundspeed > 40) || !(p.lon > 96 && p.lon < 162 && p.lat < -4 && p.lat > -46.5)) continue;
                        seen.add(p.callsign);
                        const entry = aircraft.get(p.callsign) || { g: glyph(p.callsign) };
                        entry.p = p; entry.at = now;
                        aircraft.set(p.callsign, entry);
                    }
                    for (const [callsign, entry] of aircraft) {
                        if (seen.has(callsign)) continue;
                        if (shown === entry.g) hide();
                        entry.g.remove();
                        aircraft.delete(callsign);
                    }
                }

                const live = el('live');
                function count(id, value) { el(id).textContent = value; }

                function refresh() {
                    const sectors = getJson('/api/v1/map/sectors?server=live')
                        .then(rows => count('n-sectors', drawSectors(rows)))
                        .catch(error => { count('n-sectors', '–'); throw error; });

                    const controllers = getJson('/api/v1/map/controllers?server=live')
                        .then(rows => { count('n-controllers', rows.length); count('l-controllers', rows.length === 1 ? 'controller online' : 'controllers online'); })
                        .catch(error => { count('n-controllers', '–'); throw error; });

                    // Every pilot VATSIM reports inside Australian airspace.
                    const inFirs = getJson('/api/v1/map/traffic')
                        .then(counts => count('n-aircraft', counts.in_firs))
                        .catch(error => { count('n-aircraft', '–'); throw error; });

                    const pilots = getJson('/api/v1/map/pilots').then(drawTraffic);

                    // Green only while every feed is answering.
                    Promise.allSettled([sectors, controllers, inFirs, pilots]).then(results => {
                        live.classList.toggle('on', results.every(result => result.status === 'fulfilled'));
                        place();
                    });
                }

                function frame() {
                    svg.setAttribute('viewBox', narrow.matches ? '455 90 850 760' : '431 30 950 840');
                    svg.setAttribute('preserveAspectRatio', narrow.matches ? 'xMidYMid meet' : 'xMaxYMid meet');
                    place();
                }
                narrow.addEventListener('change', frame);
                addEventListener('resize', place);
                if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
                frame();
                refresh();
                setInterval(refresh, 15000);
                setInterval(place, 2000);

                const clock = el('clock'), two = value => String(value).padStart(2, '0');
                function tick() {
                    const now = new Date();
                    clock.textContent = (live.classList.contains('on') ? 'Live ' : 'Offline ') + two(now.getUTCHours()) + ':' + two(now.getUTCMinutes()) + ':' + two(now.getUTCSeconds()) + 'Z';
                }
                setTimeout(tick, 1500);
                setInterval(tick, 1000);
            })();

            // ---------------- the plugin's windows: one at a time, at the size vatSys draws them ----------------
            (function () {
                const tabs = [...document.querySelectorAll('.tabs button')];
                if (tabs.length === 0) return;
                const shot = el('shot'), about = el('about'), caption = el('shot-name');

                function pick(index, focus) {
                    const window = SHOTS[index];
                    tabs.forEach((tab, i) => {
                        tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
                        tab.tabIndex = i === index ? 0 : -1;
                    });
                    shot.src = window.src; shot.width = window.w; shot.height = window.h; shot.alt = window.alt;
                    about.querySelector('h3').textContent = window.title;
                    about.querySelector('p').textContent = window.text;
                    caption.textContent = window.caption;
                    if (focus) tabs[index].focus();
                }

                tabs.forEach((tab, i) => {
                    tab.addEventListener('click', () => pick(i));
                    tab.addEventListener('keydown', event => {
                        if (event.key === 'ArrowRight') pick((i + 1) % tabs.length, true);
                        if (event.key === 'ArrowLeft') pick((i + tabs.length - 1) % tabs.length, true);
                    });
                });
            })();

            // ---------------- the release on offer ----------------
            getJson('/api/v1/plugin/release').then(release => {
                if (!release || !release.version) return;
                el('release-version').querySelector('dd').textContent = 'v' + release.version;
                el('release-version').hidden = false;
                el('release-version').classList.add('first');
                el('release-fits').classList.remove('first');
                const published = new Date(release.published_at);
                if (!isNaN(published)) {
                    el('release-date').querySelector('dd').textContent = published.toLocaleDateString('en-AU', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
                    el('release-date').hidden = false;
                }
            }).catch(() => {});
        </script>
    </body>
</html>
