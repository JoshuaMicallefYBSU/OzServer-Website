<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OzServer | vatSys Server Management</title>
        <meta name="description" content="OzServer keeps vatSys sector ownership, tags and ATIS in sync across every VATPAC controller.">
        <meta name="theme-color" content="#060A13">
        <meta property="og:title" content="OzServer">
        <meta property="og:description" content="One live record of who is working which sector across VATSIM Australia.">
        @if ($social = \App\Support\PublicUrl::file('images/logo/social.png'))
        <meta property="og:image" content="{{ url($social) }}">
        <meta name="twitter:card" content="summary_large_image">
        @endif
        @include('partials.icons')
        @include('partials.fonts')
        <style>
            /* One live chart of Australian airspace opens the page, beside the pitch. Everything
               after it is quiet bands on one measure that widens with the screen, and the plugin's
               own windows shown at the size vatSys draws them. */
            :root {
                /* The chart, the plinth behind each window and the install band are the scope at
                   night in both themes. */
                --night: #060A13;
                --land: #111A2D;
                --coast: #41557F;
                --grid: rgba(150, 172, 225, .11);
                --night-rule: rgba(150, 172, 225, .16);
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

                /* One measure for every band: 1180px on a laptop, wider on a wide screen, never
                   past 1440px. */
                --measure: clamp(1180px, 92vw, 1440px);
                --gutter: clamp(20px, 3.2vw, 40px);
                --band: clamp(56px, 6vw, 96px);
                --radius: 10px;
            }
            @media (prefers-color-scheme: dark) {
                :root { --bg: #0A0E18; --fg: #EEF2FB; --muted: #96A1BA; --rule: #1E273A; --accent: #86A6FF; color-scheme: dark; }
            }

            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
            body { margin: 0; background: var(--bg); color: var(--fg); font: 400 17px/1.55 var(--sans); -webkit-font-smoothing: antialiased; }
            a { color: inherit; }
            img { max-width: 100%; }
            [hidden] { display: none !important; }
            h1, h2, h3, p, ol, ul, dl, figure { margin: 0; }
            h1, h2, h3 { text-wrap: balance; font-weight: 600; }
            :focus-visible { outline: 2px solid var(--plugin); outline-offset: 3px; border-radius: 4px; }
            .wrap { max-width: var(--measure); margin: 0 auto; padding-left: max(var(--gutter), env(safe-area-inset-left, 0px)); padding-right: max(var(--gutter), env(safe-area-inset-right, 0px)); }

            .btn { display: inline-flex; align-items: center; height: 44px; padding: 0 18px; border-radius: var(--radius); font: 500 15.5px/1 var(--sans); text-decoration: none; white-space: nowrap; transition: transform .15s ease, background-color .15s ease, box-shadow .15s ease; }
            .btn:hover { transform: translateY(-1px); }
            .btn:active { transform: none; }
            .btn-light { background: var(--ice); color: #070B14; }
            .btn-light:hover { background: #fff; }
            .btn-ghost { color: var(--ice); box-shadow: inset 0 0 0 1px rgba(238, 242, 251, .28); }
            .btn-ghost:hover { background: rgba(238, 242, 251, .08); box-shadow: inset 0 0 0 1px rgba(238, 242, 251, .45); }
            .btn-small { height: 38px; padding: 0 16px; font-size: 14.5px; }

            /* ---------- hero ---------- */
            .hero { position: relative; background: var(--night); color: var(--ice); isolation: isolate; overflow: hidden; }
            .hero-top { position: relative; overflow: hidden; }
            /* The chart keeps to the right of the pitch, and to the middle 1760px of a very wide
               screen. Its ocean and graticule run on past its own box to the hero's edges. */
            .chart-box { position: absolute; top: 0; bottom: 0; right: max(0px, calc((100% - 1760px) / 2)); width: min(58%, 1020px); }
            /* One chart per region, one over another; the one not shown fades out. Lines keep their
               width on screen whatever a view's zoom. */
            .chart { position: absolute; inset: 0; display: block; width: 100%; height: 100%; overflow: visible; transition: opacity .7s ease; }
            .chart.off { opacity: 0; visibility: hidden; pointer-events: none; transition: opacity .7s ease, visibility 0s linear .7s; }
            .chart .ocean { fill: var(--night); }
            .chart .graticule { stroke: rgba(150, 172, 225, .055); stroke-width: 1; fill: none; vector-effect: non-scaling-stroke; }
            .chart .land { fill: var(--land); stroke: var(--coast); stroke-width: .9; stroke-linejoin: round; vector-effect: non-scaling-stroke; }
            .chart .shore { fill: none; stroke: var(--coast); stroke-width: .9; stroke-linejoin: round; vector-effect: non-scaling-stroke; }
            .chart .sector { fill: none; stroke: var(--grid); stroke-width: .8; vector-effect: non-scaling-stroke; }
            .chart .held { stroke-width: .8; stroke-linejoin: round; vector-effect: non-scaling-stroke; }
            .chart .held.plugin { fill: rgba(91, 140, 255, .2); stroke: rgba(132, 168, 255, .5); }
            .chart .held.external { fill: rgba(230, 190, 124, .13); stroke: rgba(230, 190, 124, .38); }
            .chart .ac .sym, .chart .ac .hist circle { fill: var(--ice); }
            .chart .ac .hit { fill: transparent; cursor: pointer; }
            .chart .ac .ring { fill: none; stroke: transparent; stroke-width: 1; }
            .chart .ac.on .sym { fill: #fff; }
            .chart .ac.on .ring { stroke: var(--ice); }

            .hero-inner { position: relative; z-index: 1; display: flex; flex-direction: column; min-height: clamp(540px, 70vh, 760px); pointer-events: none; }
            .hero-inner > * { pointer-events: auto; }

            .nav { display: flex; align-items: center; gap: 32px; height: 68px; }
            .brand { display: inline-flex; align-items: center; gap: 10px; font: 600 18px/1 var(--sans); letter-spacing: -.01em; text-decoration: none; }
            .brand svg { width: 24px; height: 24px; }
            .nav-links { display: flex; gap: 28px; margin-left: auto; font-size: 15px; }
            .nav-links a { color: var(--ice-dim); text-decoration: none; transition: color .15s; }
            .nav-links a:hover { color: var(--ice); }

            .pitch { margin-block: auto; padding-block: 32px 44px; max-width: min(540px, 38vw); pointer-events: none; }
            .pitch > * { pointer-events: auto; }
            .pitch h1 { font-size: clamp(40px, 4.6vw, 72px); line-height: 1; letter-spacing: -.038em; }
            .pitch p { margin-top: 22px; font-size: clamp(17px, 1.3vw, 19px); line-height: 1.5; color: #C3CBDD; max-width: 29em; }
            .cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }

            .rail { position: relative; border-top: 1px solid var(--night-rule); background: var(--night); }
            .rail-inner { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 40px; padding-block: 16px; }
            .live { display: inline-flex; align-items: center; gap: 9px; min-width: 11.5em; font: 500 13.5px/1 var(--mono); color: var(--ice-dim); font-variant-numeric: tabular-nums; }
            .live i { width: 8px; height: 8px; border-radius: 50%; background: #5A6377; flex: none; }
            .live.on { color: var(--ice); }
            .live.on i { background: #56D69A; animation: beat 2.4s ease-out infinite; }
            @keyframes beat { 0% { box-shadow: 0 0 0 0 rgba(86, 214, 154, .6); } 70% { box-shadow: 0 0 0 9px rgba(86, 214, 154, 0); } 100% { box-shadow: 0 0 0 0 rgba(86, 214, 154, 0); } }
            /* The region picker. While cycling, the region shown fills a line under itself over its
               30 seconds. */
            .areas { display: inline-flex; gap: 2px; padding: 3px; border-radius: 9px; box-shadow: inset 0 0 0 1px var(--night-rule); }
            .areas button { appearance: none; position: relative; overflow: hidden; border: 0; border-radius: 6px; padding: 6px 11px; background: none; color: var(--ice-dim); font: 500 13px/1 var(--sans); cursor: pointer; transition: color .15s, background-color .15s; }
            .areas button:hover { color: var(--ice); }
            .areas button[aria-pressed="true"] { background: rgba(150, 172, 225, .13); color: var(--ice); }
            .areas .auto { font-family: var(--mono); font-size: 12px; }
            .areas .auto[aria-pressed="true"] { background: none; color: #56D69A; }
            .areas button[data-region]::after { content: ""; position: absolute; left: 8px; right: 8px; bottom: 2px; height: 2px; border-radius: 1px; background: var(--plugin); transform: scaleX(0); transform-origin: left; }
            .areas.cycling button[data-region][aria-pressed="true"]::after { animation: dwell var(--dwell, 30s) linear forwards; }
            .areas.waiting button[data-region]::after { animation-play-state: paused; }
            @keyframes dwell { to { transform: scaleX(1); } }
            .fig { display: flex; align-items: baseline; gap: 9px; font-size: 14.5px; color: var(--ice-dim); }
            .fig b { min-width: 1ch; font: 500 21px/1 var(--sans); letter-spacing: -.02em; color: var(--ice); font-variant-numeric: tabular-nums; }
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
            .tip { z-index: 2; padding: 8px 11px; border-radius: 8px; background: #EEF2FB; color: #070B14; box-shadow: 0 10px 30px rgba(0, 0, 0, .45); opacity: 0; transition: opacity .12s; font-variant-numeric: tabular-nums; }
            .tip.show { opacity: 1; }
            .tip b { font-weight: 500; }
            .tip span { color: #4B556B; }

            /* ---------- bands ---------- */
            .band { padding-block: var(--band); }
            .band + .band { border-top: 1px solid var(--rule); }
            h2 { font-size: clamp(30px, 3vw, 44px); line-height: 1.06; letter-spacing: -.03em; }
            .lede { margin-top: 16px; color: var(--muted); max-width: 34em; }

            .split { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); gap: clamp(32px, 6vw, 104px); align-items: start; }
            .split > * { min-width: 0; }
            .split h2 { max-width: 17em; }
            .points { list-style: none; padding: 0; display: grid; }
            .points li { padding-block: 20px; border-top: 1px solid var(--rule); display: grid; gap: 6px; }
            .points li:first-child { border-top: 0; padding-top: 4px; }
            .points h3 { font-size: 18.5px; letter-spacing: -.012em; }
            .points p { color: var(--muted); max-width: 36em; }

            /* ---------- the plugin's windows ---------- */
            .windows { display: grid; grid-template-columns: minmax(250px, 310px) minmax(0, 1fr); gap: clamp(28px, 3.4vw, 52px); align-items: start; }
            .windows > * { min-width: 0; }
            .tabs { margin-top: 28px; display: grid; align-content: start; }
            .tabs button { appearance: none; display: block; width: 100%; padding: 14px 0; border: 0; border-top: 1px solid var(--rule); background: none; color: var(--muted); font: inherit; text-align: left; cursor: pointer; transition: color .15s; }
            .tabs button:hover { color: var(--fg); }
            .tabs button[aria-selected="true"] { color: var(--fg); cursor: default; }
            .tabs .name { display: block; font: 500 17px/1.3 var(--sans); letter-spacing: -.01em; }
            .tabs .more { display: none; margin-top: 6px; font-size: 15.5px; line-height: 1.5; color: var(--muted); max-width: 32em; }
            .tabs button[aria-selected="true"] .more { display: block; }
            .about { display: none; }

            /* Every window is on the page from the start, one on top of another in the same cell, so
               picking one shows it at once and the tallest sets the height for all of them. */
            .viewer { display: grid; place-items: center; }
            .frame { grid-area: 1 / 1; min-width: 0; max-width: 100%; padding: clamp(10px, 1.4vw, 18px); border-radius: 12px; background: var(--night); box-shadow: inset 0 0 0 1px rgba(150, 172, 225, .14); opacity: 0; visibility: hidden; transition: opacity .18s ease, visibility 0s linear .18s; }
            .frame.shown { opacity: 1; visibility: visible; transition: opacity .18s ease; }
            .frame img { display: block; max-width: 100%; height: auto; box-shadow: 0 14px 40px rgba(0, 0, 0, .55); }

            /* ---------- a session ---------- */
            .steps { list-style: none; padding: 0; margin-top: 40px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 clamp(24px, 4vw, 64px); counter-reset: step; }
            .steps li { counter-increment: step; padding-top: 20px; border-top: 1px solid var(--rule); display: grid; gap: 8px; align-content: start; }
            .steps li::before { content: counter(step); font: 500 14px/1 var(--mono); color: var(--muted); }
            .steps h3 { font-size: 21px; letter-spacing: -.018em; margin-top: 8px; }
            .steps p { color: var(--muted); max-width: 30em; }

            /* ---------- install ---------- */
            .install { background: var(--night); color: var(--ice); }
            .install .wrap { padding-block: var(--band); }
            .install .lede { color: #B4BDD2; }
            .install ol { margin-top: 24px; padding: 0; list-style: none; counter-reset: i; display: grid; gap: 12px; color: #D5DBEA; }
            .install ol li { counter-increment: i; display: grid; grid-template-columns: 28px minmax(0, 1fr); align-items: baseline; }
            .install ol li::before { content: counter(i); font: 500 13.5px/1 var(--mono); color: var(--ice-dim); }
            .install code { font: 500 .88em/1 var(--mono); color: var(--ice); background: rgba(150, 172, 225, .12); padding: 3px 7px; border-radius: 5px; overflow-wrap: anywhere; }
            .release { display: grid; gap: 24px; max-width: 520px; justify-self: end; width: 100%; }
            .release dl { display: grid; }
            .release dl div { display: flex; justify-content: space-between; gap: 16px; padding-block: 12px; border-top: 1px solid var(--night-rule); font-size: 15.5px; }
            .release dl div:last-child { border-bottom: 1px solid var(--night-rule); }
            .release dt { color: var(--ice-dim); }
            .release dd { margin: 0; font: 500 14.5px/1.5 var(--mono); text-align: right; }
            .release .cta { margin-top: 0; }

            footer { padding-block: 30px; font-size: 14px; color: var(--muted); }
            footer .wrap { display: flex; flex-wrap: wrap; gap: 10px 28px; align-items: center; }
            .foot-brand { display: inline-flex; align-items: center; gap: 9px; color: var(--fg); }
            .foot-brand svg { width: 18px; height: 18px; flex: none; }
            footer a { text-decoration: none; transition: color .15s; }
            footer a:hover { color: var(--fg); }
            footer .note { margin-left: auto; }

            /* ---------- laptops and tablets on their side: the windows get the full width ---------- */
            @media (max-width: 1340px) {
                /* Beside the list the largest window would be too small to read. Their names go in a
                   row to swipe, then the words about the one picked, then the window itself. */
                .windows { grid-template-columns: minmax(0, 1fr); gap: 24px; }
                .tabs { margin-top: 24px; display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin-inline: calc(-1 * var(--gutter)); padding-inline: var(--gutter); scroll-padding-inline: var(--gutter); }
                .tabs::-webkit-scrollbar { display: none; }
                .tabs button { width: auto; flex: none; min-height: 44px; padding: 0 16px; border: 0; border-radius: 999px; box-shadow: inset 0 0 0 1px var(--rule); white-space: nowrap; }
                .tabs button[aria-selected="true"] { background: var(--fg); color: var(--bg); box-shadow: none; }
                .tabs .name { font-size: 15px; }
                .tabs button[aria-selected="true"] .more { display: none; }
                .about { display: block; margin-top: 20px; }
                .about h3 { font-size: 19px; letter-spacing: -.015em; }
                .about p { margin-top: 6px; color: var(--muted); max-width: 40em; }
                /* Under its words rather than centred in the room kept for the tallest window. */
                .viewer { place-items: start; }
            }

            /* ---------- tablets and phones: one column, the chart under the pitch ---------- */
            @media (max-width: 960px) {
                .nav-links { display: none; }
                .nav .btn { margin-left: auto; }
                .hero-top { display: flex; flex-direction: column; }
                .hero-inner { min-height: 0; order: 1; }
                .chart-box { position: relative; right: auto; width: 100%; max-width: 760px; margin: 0 auto; aspect-ratio: 850 / 760; order: 2; }
                .pitch { padding-block: 20px 28px; max-width: 34em; }
                .pitch h1 { font-size: clamp(38px, 8vw, 60px); }
                .rail-inner { gap: 12px 28px; }
                .live { flex-basis: 100%; }
                .legend { margin-left: 0; flex-basis: 100%; }
                .split { grid-template-columns: 1fr; gap: 28px; }
                .points li:first-child { border-top: 1px solid var(--rule); padding-top: 20px; }
                .release { justify-self: start; }

                .steps { grid-template-columns: 1fr; gap: 28px; margin-top: 32px; }
                footer .note { margin-left: 0; flex-basis: 100%; }
            }
            @media (max-width: 700px) {
                /* On a phone the tall, narrow windows would set the height for the wide ones and leave
                   a gap under them, so nothing is drawn taller than the wide ones come out. */
                .frame img { width: auto; max-height: 58vw; }
            }
            @media (max-width: 520px) {
                body { font-size: 16px; }
                .cta .btn { flex: 1 1 auto; justify-content: center; }
                .fig { flex: 1 1 40%; }
                .release dl div { flex-direction: column; gap: 2px; }
                .release dd { text-align: left; }
            }
            @media (prefers-reduced-motion: reduce) {
                html { scroll-behavior: auto; }
                .live.on i { animation: none; }
                .chart, .chart.off { transition: none; }
                .btn, .frame, .frame.shown { transition: none; }
            }
        </style>
    </head>
    <body>
        <header class="hero" id="top">
            <div class="hero-top">
                <div class="chart-box">
                    {{-- One chart per region (App\Support\ChartViews), stacked: the page shows one at a
                         time, cycling through them. --}}
                    @foreach ($views as $region => $view)
                    <svg class="chart{{ $loop->first ? '' : ' off' }}" data-region="{{ $region }}" viewBox="{{ $view['viewBox'] }}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Chart of {{ $view['airspace'] }} showing staffed sectors and aircraft" @if (! $loop->first) aria-hidden="true" @endif>
                        <rect class="ocean" x="-4000" y="-3000" width="9400" height="6900"/>
                        <path class="graticule" d="{{ $view['graticule'] }}"/>
                        <path class="land" d="{{ $view['coast']['land'] }}"/>
                        @if ($view['coast']['shore'] !== '')<path class="shore" d="{{ $view['coast']['shore'] }}"/>@endif
                        <path class="sector" d="{{ $view['sectorsPath'] }}"/>
                        <g class="held-layer"></g>
                        <g class="traffic-layer"></g>
                    </svg>
                    @endforeach
                </div>

                <div class="hero-inner wrap">
                    <nav class="nav" aria-label="Main">
                        <a class="brand" href="/">
                            @include('partials.mark')
                            OzServer
                        </a>
                        <div class="nav-links">
                            <a href="/live-map">Live map</a>
                            <a href="/how-to">How-To</a>
                            <a href="#windows">In vatSys</a>
                        </div>
                        <a class="btn btn-light btn-small" href="#install">Install</a>
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
                    {{-- The region shown. Cycles every 30 seconds until one is picked; Auto resumes. --}}
                    <span class="areas" role="group" aria-label="Airspace shown">
                        @foreach ($views as $region => $view)
                        <button type="button" data-region="{{ $region }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $view['label'] }}</button>
                        @endforeach
                        <button type="button" class="auto" id="auto" aria-pressed="true" title="Cycle through every region, 30 seconds each">Auto</button>
                    </span>
                    <span class="fig"><b id="n-controllers">&ndash;</b> <span id="l-controllers">controllers online</span></span>
                    <span class="fig"><b id="n-sectors">&ndash;</b> sectors staffed</span>
                    <span class="fig"><b id="n-aircraft">&ndash;</b> <span>aircraft in <span id="l-airspace">{{ \Illuminate\Support\Arr::first($views)['airspace'] }}</span></span></span>
                    <span class="legend">
                        <span><i class="k-plugin"></i>On OzServer</span>
                        <span><i class="k-external"></i>Without the plugin</span>
                    </span>
                </div>
            </div>
            <div id="chips"></div>
            <div class="tip" id="tip"></div>
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
                <div class="wrap windows">
                    <div>
                        <h2>It lives inside vatSys.</h2>
                        <p class="lede">These are the plugin's own windows, drawn by vatSys with the Australia profile's colours.</p>

                        <div class="tabs" role="tablist" aria-label="Plugin windows" aria-orientation="vertical">
                            @foreach ($windows as $index => $window)
                            <button type="button" role="tab" id="tab-{{ $index }}" aria-controls="window-{{ $index }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? '0' : '-1' }}">
                                <span class="name">{{ $window['tab'] }}</span>
                                <span class="more">{{ $window['text'] }}</span>
                            </button>
                            @endforeach
                        </div>
                        {{-- Shown in place of the words under each name where the names run in a row. --}}
                        <div class="about" id="about">
                            <h3>{{ $windows[0]['title'] }}</h3>
                            <p>{{ $windows[0]['text'] }}</p>
                        </div>
                    </div>

                    <div class="viewer">
                        @foreach ($windows as $index => $window)
                        <figure class="frame{{ $index === 0 ? ' shown' : '' }}" id="window-{{ $index }}" role="tabpanel" aria-labelledby="tab-{{ $index }}" data-title="{{ $window['title'] }}" data-text="{{ $window['text'] }}">
                            {{-- All of them are fetched with the page, the first ahead of the rest. --}}
                            <img src="{{ $window['src'] }}" alt="{{ $window['alt'] }}" width="{{ $window['w'] }}" height="{{ $window['h'] }}" loading="eager" decoding="async" fetchpriority="{{ $index === 0 ? 'auto' : 'low' }}">
                        </figure>
                        @endforeach
                    </div>
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
                        <div><dt>Works with</dt><dd>vatSys, Australia and Pacific profiles</dd></div>
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
                    @include('partials.mark', ['ring' => 'currentColor', 'held' => 'var(--accent)'])
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
            // The chart's views, one per region (App\Support\ChartViews): projection, framing and
            // extent. Their outlines and coastlines are already in each region's <svg>.
            const VIEWS = @json($chartViews);
            const REGIONS = Object.keys(VIEWS);
            const DWELL_MS = 30000;

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
            // on every request. Read every 15 seconds while the page is in view. Between reads each
            // aircraft is carried along its heading at its groundspeed, so the picture moves rather
            // than jumping.
            //
            // One region is shown at a time - Australia (with the Pacific), New Zealand (with its
            // oceanic airspace), Mexico - each for 30 seconds in turn, until a visitor picks one.
            // The figures under the chart are the region's own.
            (function () {
                const hero = document.querySelector('.hero');
                const charts = Object.fromEntries([...document.querySelectorAll('.chart[data-region]')].map(svg => [svg.dataset.region, svg]));
                const chips = el('chips'), tip = el('tip'), areas = document.querySelector('.areas'), autoButton = el('auto');
                const NS = 'http://www.w3.org/2000/svg';
                const narrow = matchMedia('(max-width: 960px)');
                const still = matchMedia('(prefers-reduced-motion: reduce)');
                const aircraft = new Map();   // callsign -> { p, g, at }, for the region shown
                let anchors = [];             // one per chip: where on the chart it sits

                // A region named in the address (?region=new-zealand) is shown and kept.
                const asked = new URLSearchParams(location.search).get('region');
                let region = REGIONS.includes(asked) ? asked : REGIONS[0];
                let auto = !REGIONS.includes(asked);
                let elapsed = 0;              // ms the region shown has been on screen, while cycling
                const last = { sectors: null, controllers: null, traffic: null };

                const svg = () => charts[region];
                const layer = name => svg().querySelector('.' + name + '-layer');
                function px(lon) {
                    const P = VIEWS[region].projection;
                    if (P.wrap && lon < 0) lon += 360;
                    return (lon - P.lon0) * P.c * P.k;
                }
                const py = lat => (VIEWS[region].projection.lat0 - lat) * VIEWS[region].projection.k;
                function onChart(lat, lon, margin) {
                    const e = VIEWS[region].extent;
                    if (VIEWS[region].projection.wrap && lon < 0) lon += 360;
                    return lat >= e.lat[0] - margin && lat <= e.lat[1] + margin && lon >= e.lon[0] - margin && lon <= e.lon[1] + margin;
                }

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
                    layer('traffic').appendChild(g);
                    return g;
                }

                function place() {
                    const ctm = svg().getScreenCTM();
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
                    // Each controller's label on their own sector. Two controllers close together
                    // (a tower inside a centre's airspace) would land on top of each other, so a
                    // label that would cover one already placed is moved down to sit under it.
                    const box = hero.getBoundingClientRect(), placed = [];
                    [...chips.children].map((chip, i) => ({ chip, at: new DOMPoint(anchors[i].x, anchors[i].y).matrixTransform(ctm) }))
                        .sort((a, b) => a.at.y - b.at.y)
                        .forEach(({ chip, at }) => {
                            const width = chip.offsetWidth, height = chip.offsetHeight;
                            const left = Math.min(Math.max(8, at.x - box.left - width / 2), box.width - width - 8);
                            let top = at.y - box.top - height / 2;
                            for (let moved = true; moved;) {
                                moved = false;
                                for (const other of placed) {
                                    if (left < other.left + other.width + 4 && other.left < left + width + 4 && top < other.top + other.height + 3 && other.top < top + height + 3) {
                                        top = other.top + other.height + 3;
                                        moved = true;
                                    }
                                }
                            }
                            placed.push({ left, top, width, height });
                            chip.style.transform = 'translate(' + Math.round(left) + 'px, ' + Math.round(top) + 'px)';
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

                    const ctm = svg().getScreenCTM(), box = hero.getBoundingClientRect();
                    const point = new DOMPoint(entry.x, entry.y).matrixTransform(ctm);
                    const left = Math.min(Math.max(8, point.x - box.left + 14), box.width - tip.offsetWidth - 8);
                    const top = Math.max(8, point.y - box.top - tip.offsetHeight - 12);
                    tip.style.transform = 'translate(' + Math.round(left) + 'px, ' + Math.round(top) + 'px)';
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

                // A sector's region, from the API; one from before it said is Australian, as all were.
                const inRegion = row => (row.region || 'australia') === region;

                // The region's staffed sectors, shaded by whether their controller is on OzServer,
                // and one label per controller on the sector they are logged on as.
                function drawSectors(sectors) {
                    const owned = sectors.filter(sector => sector.owner && sector.owner.callsign && inRegion(sector));
                    layer('held').replaceChildren(...owned.map(sector => node('path', {
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
                        if (!(p.groundspeed > 40) || !onChart(p.lat, p.lon, 0.5)) continue;
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

                // The figures and shading for the region shown, from the last reads.
                function render() {
                    if (last.sectors) count('n-sectors', drawSectors(last.sectors));
                    if (last.controllers) {
                        const n = last.controllers.filter(inRegion).length;
                        count('n-controllers', n);
                        count('l-controllers', n === 1 ? 'controller online' : 'controllers online');
                    }
                    if (last.traffic) {
                        const own = last.traffic.regions && last.traffic.regions[region];
                        count('n-aircraft', own ? own.in_firs : (region === 'australia' && !last.traffic.regions ? last.traffic.in_firs : '–'));
                    }
                    count('l-airspace', VIEWS[region].airspace);
                    place();
                }

                function readPilots() {
                    const asking = region;
                    return getJson('/api/v1/map/pilots?region=' + encodeURIComponent(asking))
                        .then(pilots => { if (asking === region) { drawTraffic(pilots); place(); } });
                }

                function refresh() {
                    // Nobody is looking: the next read waits until they are.
                    if (document.hidden) return;

                    const sectors = getJson('/api/v1/map/sectors?server=live')
                        .then(rows => { last.sectors = rows; })
                        .catch(error => { last.sectors = null; count('n-sectors', '–'); throw error; });

                    const controllers = getJson('/api/v1/map/controllers?server=live')
                        .then(rows => { last.controllers = rows; })
                        .catch(error => { last.controllers = null; count('n-controllers', '–'); throw error; });

                    // Every pilot VATSIM reports inside each region's airspace.
                    const inFirs = getJson('/api/v1/map/traffic')
                        .then(counts => { last.traffic = counts; })
                        .catch(error => { last.traffic = null; count('n-aircraft', '–'); throw error; });

                    // Green only while every feed is answering.
                    Promise.allSettled([sectors, controllers, inFirs, readPilots()]).then(results => {
                        live.classList.toggle('on', results.every(result => result.status === 'fulfilled'));
                        render();
                    });
                }

                // Shows another region: its chart fades in, the last one's aircraft and labels go,
                // and the figures are the new region's at once, from the last reads.
                function showRegion(next) {
                    if (next === region) return;
                    hide();
                    for (const entry of aircraft.values()) entry.g.remove();
                    aircraft.clear();
                    layer('held').replaceChildren();
                    region = next;
                    elapsed = 0;
                    for (const [name, chart] of Object.entries(charts)) {
                        chart.classList.toggle('off', name !== region);
                        if (name === region) chart.removeAttribute('aria-hidden'); else chart.setAttribute('aria-hidden', 'true');
                    }
                    frame();
                    markButtons();
                    render();
                    if (!document.hidden) readPilots().catch(() => {});
                }

                function markButtons() {
                    for (const button of areas.querySelectorAll('[data-region]')) {
                        const on = button.dataset.region === region;
                        button.setAttribute('aria-pressed', on ? 'true' : 'false');
                    }
                    autoButton.setAttribute('aria-pressed', auto ? 'true' : 'false');
                    // Off and on again, so the dwell line starts over for the region now shown.
                    areas.classList.remove('cycling');
                    void areas.offsetWidth;
                    areas.classList.toggle('cycling', auto && !still.matches);
                }

                // Picking a region keeps it; Auto goes back to cycling, from the region shown.
                for (const button of areas.querySelectorAll('[data-region]')) {
                    button.addEventListener('click', () => { auto = false; showRegion(button.dataset.region); markButtons(); });
                }
                autoButton.addEventListener('click', () => { auto = !auto; elapsed = 0; markButtons(); });
                areas.style.setProperty('--dwell', DWELL_MS + 'ms');

                // On to the next region once this one has had its time - not while an aircraft's
                // details are open, or while nobody is looking.
                setInterval(() => {
                    const waiting = document.hidden || !!shown;
                    areas.classList.toggle('waiting', waiting);
                    if (!auto || waiting) return;
                    elapsed += 500;
                    if (elapsed >= DWELL_MS) showRegion(REGIONS[(REGIONS.indexOf(region) + 1) % REGIONS.length]);
                }, 500);

                function frame() {
                    const view = VIEWS[region];
                    svg().setAttribute('viewBox', narrow.matches ? view.narrow : view.viewBox);
                    place();
                }
                narrow.addEventListener('change', frame);
                addEventListener('resize', place);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
                if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
                if (region !== REGIONS[0]) {
                    // Opened on another region: its chart is the one shown from the start.
                    for (const [name, chart] of Object.entries(charts)) chart.classList.toggle('off', name !== region);
                }
                frame();
                markButtons();
                count('l-airspace', VIEWS[region].airspace);
                refresh();
                setInterval(refresh, 15000);
                setInterval(() => { if (!document.hidden) place(); }, 2000);

                const clock = el('clock'), two = value => String(value).padStart(2, '0');
                function tick() {
                    const now = new Date();
                    clock.textContent = (live.classList.contains('on') ? 'Live ' : 'Offline ') + two(now.getUTCHours()) + ':' + two(now.getUTCMinutes()) + ':' + two(now.getUTCSeconds()) + 'Z';
                }
                setTimeout(tick, 1500);
                setInterval(tick, 1000);
            })();

            // ---------------- the plugin's windows ----------------
            //
            // Every picture is already on the page (see .viewer), so picking a window only changes
            // which one is shown: nothing is fetched and nothing moves.
            (function () {
                const tabs = [...document.querySelectorAll('.tabs [role="tab"]')];
                if (tabs.length === 0) return;
                const frames = tabs.map(tab => el(tab.getAttribute('aria-controls')));
                const about = el('about');

                function pick(index, focus) {
                    tabs.forEach((tab, i) => {
                        tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
                        tab.tabIndex = i === index ? 0 : -1;
                        frames[i].classList.toggle('shown', i === index);
                    });
                    about.querySelector('h3').textContent = frames[index].dataset.title;
                    about.querySelector('p').textContent = frames[index].dataset.text;
                    if (focus) tabs[index].focus();
                    // Where the names run in a row, the one picked is brought into view.
                    if (tabs[index].scrollIntoView && getComputedStyle(tabs[index].parentElement).overflowX === 'auto')
                        tabs[index].scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }

                tabs.forEach((tab, i) => {
                    tab.addEventListener('click', () => pick(i));
                    tab.addEventListener('keydown', event => {
                        const step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];
                        if (step) { event.preventDefault(); pick((i + step + tabs.length) % tabs.length, true); }
                        if (event.key === 'Home') { event.preventDefault(); pick(0, true); }
                        if (event.key === 'End') { event.preventDefault(); pick(tabs.length - 1, true); }
                    });
                });

                // The words about each window are different lengths. Wherever they are shown - under
                // each name beside the windows, or in their own block where the names run in a row -
                // that place is made as tall as its longest state from the start, so picking another
                // window moves nothing below it. Measured rather than guessed, since it depends on
                // the width and the font.
                const list = tabs[0].parentElement;
                function settle() {
                    list.style.minHeight = about.style.minHeight = '';
                    const current = tabs.findIndex(tab => tab.getAttribute('aria-selected') === 'true');
                    const heading = about.querySelector('h3'), words = about.querySelector('p');
                    const inRow = getComputedStyle(about).display !== 'none';
                    let tallest = 0;
                    tabs.forEach((tab, i) => {
                        if (inRow) {
                            heading.textContent = frames[i].dataset.title;
                            words.textContent = frames[i].dataset.text;
                            tallest = Math.max(tallest, about.offsetHeight);
                        } else {
                            tabs.forEach((other, j) => other.setAttribute('aria-selected', i === j ? 'true' : 'false'));
                            tallest = Math.max(tallest, list.offsetHeight);
                        }
                    });
                    tabs.forEach((tab, i) => tab.setAttribute('aria-selected', i === current ? 'true' : 'false'));
                    heading.textContent = frames[current].dataset.title;
                    words.textContent = frames[current].dataset.text;
                    (inRow ? about : list).style.minHeight = tallest + 'px';
                }
                let settling = 0;
                addEventListener('resize', () => { clearTimeout(settling); settling = setTimeout(settle, 120); });
                if (document.fonts && document.fonts.ready) document.fonts.ready.then(settle);
                settle();

                // Decoded ahead of time as well as fetched, so the first showing of each is not the
                // moment the browser unpacks it.
                addEventListener('load', () => {
                    frames.forEach(frame => {
                        const image = frame.querySelector('img');
                        if (image && image.decode) image.decode().catch(() => {});
                    });
                });
            })();

            // ---------------- the release on offer ----------------
            getJson('/api/v1/plugin/release').then(release => {
                if (!release || !release.version) return;
                el('release-version').querySelector('dd').textContent = 'v' + release.version;
                el('release-version').hidden = false;
                const published = new Date(release.published_at);
                if (!isNaN(published)) {
                    el('release-date').querySelector('dd').textContent = published.toLocaleDateString('en-AU', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
                    el('release-date').hidden = false;
                }
            }).catch(() => {});
        </script>
    </body>
</html>
