<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>How to use OzServer | OzServer</title>
        @include('partials.icons')
        <meta name="description" content="A controller's guide to the OzServer vatSys plugin: claiming and handing over sectors, requests, tags, disconnects and the Arrival List.">
        @include('partials.fonts')
        <style>
            /* The guide, in the homepage's own terms: the night band across the top, then a plain
               document on the page colour with the section list beside it. */
            :root {
                --night: #060A13;
                --ice: #EEF2FB;
                --ice-dim: #9AA6C2;
                --plugin: #5B8CFF;

                --bg: #F7F8FA;
                --fg: #0A0F1C;
                --text: #2A3246;
                --muted: #5A6377;
                --rule: #DCE0E8;
                --tint: #ECEFF4;
                --accent: #2450E6;
                --warn: #B4610A;
                --warn-tint: #FBF1E2;

                --incoming: rgb(255, 235, 0);    /* RequestedSectorOverlay colours */
                --outgoing: rgb(144, 238, 144);
                --releasing: rgb(255, 120, 110);

                --sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
                --mono: "Geist Mono", ui-monospace, "Cascadia Mono", Consolas, monospace;
                --topbar: 64px;
                --measure: 1180px;
            }
            @media (prefers-color-scheme: dark) {
                :root { --bg: #0A0E18; --fg: #EEF2FB; --text: #C9D1E3; --muted: #96A1BA; --rule: #1E273A; --tint: #131B2C; --accent: #86A6FF; --warn: #E6BE7C; --warn-tint: #1E1A12; color-scheme: dark; }
            }

            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; scroll-padding-top: calc(var(--topbar) + 20px); }
            body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--text); font: 400 16px/1.6 var(--sans); -webkit-font-smoothing: antialiased; }
            a { color: inherit; }
            :focus-visible { outline: 2px solid var(--plugin); outline-offset: 3px; border-radius: 4px; }
            .wrap { max-width: var(--measure); margin: 0 auto; padding-left: max(24px, env(safe-area-inset-left, 0px)); padding-right: max(24px, env(safe-area-inset-right, 0px)); }

            /* ---------- the night band: site header and the guide's title ---------- */
            .oz-topbar { position: sticky; top: 0; z-index: 10; background: var(--night); color: var(--ice); border-bottom: 1px solid rgba(150, 172, 225, .16); }
            .oz-topbar .wrap { display: flex; align-items: center; gap: 28px; height: var(--topbar); }
            .oz-brand { display: inline-flex; align-items: center; gap: 10px; font: 600 18px/1 var(--sans); letter-spacing: -.01em; text-decoration: none; white-space: nowrap; }
            .oz-brand svg { width: 24px; height: 24px; }
            .oz-links { display: flex; gap: 28px; margin-left: auto; font-size: 15px; }
            .oz-links a { color: var(--ice-dim); text-decoration: none; transition: color .15s; }
            .oz-links a:hover, .oz-links a[aria-current] { color: var(--ice); }
            .oz-btn { display: inline-flex; align-items: center; height: 38px; padding: 0 16px; border-radius: 10px; background: var(--ice); color: #070B14; font: 500 14.5px/1 var(--sans); text-decoration: none; white-space: nowrap; }

            .oz-intro { background: var(--night); color: var(--ice); }
            .oz-intro .wrap { padding-block: clamp(44px, 6vw, 76px); }
            .oz-intro h1 { margin: 0; font-size: clamp(34px, 4.4vw, 56px); line-height: 1.02; letter-spacing: -.035em; font-weight: 600; text-wrap: balance; }
            .oz-intro p { margin: 18px 0 0; color: #C3CBDD; font-size: 18px; line-height: 1.5; max-width: 38em; }

            /* ---------- layout: section list + document ---------- */
            .oz-layout { display: grid; grid-template-columns: 232px minmax(0, 1fr); gap: clamp(32px, 5vw, 72px); align-items: start; padding-block: 40px 0; }
            .oz-side { position: sticky; top: calc(var(--topbar) + 24px); max-height: calc(100vh - var(--topbar) - 48px); overflow-y: auto; scrollbar-width: thin; font-size: 14.5px; }
            .oz-side summary { display: none; }
            .oz-side h4 { margin: 22px 0 6px; font-size: 13px; font-weight: 500; color: var(--fg); }
            .oz-side h4:first-of-type { margin-top: 0; }
            .oz-side ul { list-style: none; margin: 0; padding: 0; }
            .oz-side a { display: block; padding: 4px 0; color: var(--muted); text-decoration: none; transition: color .15s; }
            .oz-side a:hover { color: var(--fg); }
            .oz-side a.active { color: var(--accent); font-weight: 500; }

            .oz-content { min-width: 0; max-width: 780px; }
            .oz-doc { padding-block: 44px; border-top: 1px solid var(--rule); }
            .oz-doc:first-child { border-top: 0; padding-top: 0; }
            /* The section list already says which group a section is in. */
            .oz-doc > .oz-kicker { display: none; }
            .oz-doc h2 { margin: 0; font-size: 30px; line-height: 1.1; letter-spacing: -.025em; font-weight: 600; color: var(--fg); text-wrap: balance; }
            .oz-doc h3 { margin: 30px 0 0; font-size: 18.5px; letter-spacing: -.012em; font-weight: 600; color: var(--fg); }
            .oz-doc p { margin: 12px 0 0; }
            .oz-doc ul, .oz-doc ol { margin: 12px 0 0; padding-left: 22px; }
            .oz-doc li { margin-top: 6px; }
            .oz-doc b, .oz-doc strong { color: var(--fg); font-weight: 600; }
            .oz-doc a { color: var(--accent); text-underline-offset: 3px; }
            .oz-doc code { font: 500 .86em/1 var(--mono); background: var(--tint); padding: 2px 6px; border-radius: 5px; color: var(--fg); overflow-wrap: anywhere; }

            /* A vatSys control or menu entry, written the way it reads on screen. */
            .ui { font: 500 .84em/1.5 var(--mono); color: var(--fg); background: var(--tint); box-shadow: inset 0 0 0 1px var(--rule); border-radius: 5px; padding: 1px 6px; white-space: nowrap; }
            .oz-doc a.ui { color: var(--accent); }

            .oz-table { width: 100%; margin-top: 16px; border-collapse: collapse; font-size: 15px; }
            .oz-table th, .oz-table td { text-align: left; vertical-align: top; padding: 11px 16px 11px 0; border-top: 1px solid var(--rule); }
            .oz-table th { color: var(--muted); font-weight: 500; font-size: 13.5px; border-top: 0; padding-top: 0; }
            .oz-table td:first-child { color: var(--fg); width: 40%; }

            .oz-note { margin-top: 18px; padding: 14px 18px; border-radius: 10px; background: var(--tint); color: var(--text); }
            .oz-note.warn { background: var(--warn-tint); }
            .oz-note.warn b:first-child { color: var(--warn); }

            .oz-swatch { display: inline-block; width: 11px; height: 11px; border-radius: 3px; vertical-align: -1px; margin-right: 6px; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .35); }

            /* ---------- screenshots: the plugin's own windows, on the scope at night ---------- */
            .oz-shot { margin: 22px 0 0; }
            .oz-shot button { all: unset; cursor: zoom-in; display: grid; place-items: center; box-sizing: border-box; width: 100%; padding: clamp(16px, 4vw, 36px); border-radius: 14px; background: var(--night); box-shadow: inset 0 0 0 1px rgba(150, 172, 225, .14); }
            .oz-shot button:focus-visible { outline: 2px solid var(--plugin); outline-offset: 3px; }
            .oz-shot img { display: block; max-width: 100%; height: auto; box-shadow: 0 18px 50px rgba(0, 0, 0, .55); }
            .oz-shot figcaption { margin-top: 10px; font-size: 14px; color: var(--muted); }
            .oz-shot.pending .oz-shot-slot { display: flex; flex-direction: column; justify-content: center; gap: 4px; min-height: 140px; padding: 16px 18px; border: 1px dashed var(--rule); border-radius: 14px; color: var(--muted); font-size: 14px; }
            .oz-shot.pending .oz-shot-slot code { align-self: flex-start; }
            .oz-zoom { border: 0; padding: 0; background: transparent; max-width: 96vw; max-height: 94vh; }
            .oz-zoom::backdrop { background: rgba(6, 10, 19, .88); }
            .oz-zoom img { display: block; max-width: 96vw; max-height: 94vh; cursor: zoom-out; }

            footer { margin-top: 56px; border-top: 1px solid var(--rule); padding-block: 34px; font-size: 14px; color: var(--muted); }
            footer .wrap { display: flex; flex-wrap: wrap; gap: 10px 28px; align-items: center; }
            .foot-brand { display: inline-flex; align-items: center; gap: 9px; color: var(--fg); }
            .foot-brand svg { width: 18px; height: 18px; flex: none; }
            footer a { text-decoration: none; }
            footer a:hover { color: var(--fg); }

            /* Narrow windows, including vatSys's own browser at its default size: the section list
               folds into a "Jump to a section" list above the document. */
            @media (max-width: 900px) {
                .oz-links { display: none; }
                .oz-topbar .oz-btn { margin-left: auto; }
                .oz-layout { grid-template-columns: minmax(0, 1fr); gap: 28px; padding-block: 24px 0; }
                .oz-side { position: static; max-height: none; padding: 14px 18px; border-radius: 12px; background: var(--tint); }
                .oz-side summary { display: block; cursor: pointer; font-weight: 500; color: var(--fg); }
                .oz-side details[open] summary { margin-bottom: 14px; }
                .oz-side ul { columns: 2 180px; }
                .oz-doc h2 { font-size: 26px; }
            }
            @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
        </style>
    </head>
    <body>
        @php
            // Where the public folder sits in the site's URLs: '' when it is the web root, as on a
            // development machine, and '/public' on a host that serves the project folder itself,
            // as ozserver.org does. Worked out from the web server's document root rather than
            // configured, so a screenshot shows wherever the site is put.
            $publicUrl = \App\Support\PublicUrl::prefix();

            // Only someone running the site on their own machine is shown what is still missing.
            $host = request()->getHost();
            $onDevMachine = ! app()->environment('production')
                && (in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.localhost'));

            // A screenshot from public/images/how-to/. One not yet added shows, on a development
            // machine, as a slot saying what belongs there; anywhere else it is simply left out.
            $shot = function (string $file, string $caption) use ($publicUrl, $onDevMachine) {
                $relative = 'images/how-to/'.$file;
                $absolute = public_path($relative);
                if (is_file($absolute)) {
                    $src = $publicUrl.'/'.$relative.'?v='.filemtime($absolute);
                    return '<figure class="oz-shot"><button type="button" data-zoom="'.e($src).'" aria-label="Enlarge screenshot">'
                        .'<img src="'.e($src).'" alt="'.e($caption).'" loading="lazy"></button>'
                        .'<figcaption>'.e($caption).'</figcaption></figure>';
                }
                if (! $onDevMachine) {
                    return '';
                }
                return '<figure class="oz-shot pending"><div class="oz-shot-slot"><b>Screenshot to add</b>'
                    .'<span>'.e($caption).'</span><code>public/'.e($relative).'</code></div></figure>';
            };
        @endphp

        <header class="oz-topbar">
            <div class="wrap">
                <a href="/" class="oz-brand">@include('partials.mark')OzServer</a>
                <nav class="oz-links" aria-label="Main">
                    <a href="/live-map">Live map</a>
                    <a href="/how-to" aria-current="page">How-To</a>
                </nav>
                <a href="/#install" class="oz-btn">Install</a>
            </div>
        </header>

        <section class="oz-intro">
            <div class="wrap">
                <h1>How to use OzServer</h1>
                <p>Everything you need to control with the OzServer plugin for vatSys: how to take airspace, hand it over, deal with requests, and what happens when someone disconnects.</p>
            </div>
        </section>

        <div class="oz-layout wrap">
            <nav class="oz-side" aria-label="Guide sections">
                <details id="oz-side-details" open>
                    <summary>Jump to a section</summary>
                    <h4>Getting started</h4>
                    <ul>
                        <li><a href="#what-is-ozserver">What OzServer does</a></li>
                        <li><a href="#installing">Installing</a></li>
                        <li><a href="#menus">Where to find it</a></li>
                        <li><a href="#logging-on">Logging on</a></li>
                    </ul>
                    <h4>Sectors</h4>
                    <ul>
                        <li><a href="#transmitting">Claiming by transmitting</a></li>
                        <li><a href="#sectors-window">The Sectors window</a></li>
                        <li><a href="#requesting">Asking for a sector</a></li>
                        <li><a href="#answering">Answering a request</a></li>
                        <li><a href="#releasing">Releasing sectors</a></li>
                        <li><a href="#extension-limits">Extension limits</a></li>
                    </ul>
                    <h4>Traffic</h4>
                    <ul>
                        <li><a href="#tags">Aircraft and tags</a></li>
                        <li><a href="#arrival-list">Arrival List</a></li>
                        <li><a href="#atis">ATIS</a></li>
                    </ul>
                    <h4>Other controllers</h4>
                    <ul>
                        <li><a href="#no-plugin">Controllers without OzServer</a></li>
                        <li><a href="#disconnects">Disconnects and reconnects</a></li>
                        <li><a href="#roles">Observers, FMP and SweatBox</a></li>
                    </ul>
                    <h4>Help</h4>
                    <ul>
                        <li><a href="#updates">Updates</a></li>
                        <li><a href="#disabling">Turning OzServer off</a></li>
                        <li><a href="#troubleshooting">Troubleshooting</a></li>
                    </ul>
                </details>
            </nav>

            <main class="oz-content">
                <!-- ================= Getting started ================= -->
                <section id="what-is-ozserver" class="oz-doc">
                    <p class="oz-kicker">Getting started</p>
                    <h2>What OzServer does</h2>
                    <p>OzServer gives every controller the same picture of <b>who owns which sector</b>. Exactly one controller owns a sector at a time, and everyone's vatSys agrees on who that is.</p>
                    <ul>
                        <li><b>Taking airspace</b> works the way you already control: log on, or transmit on a VSCS line.</li>
                        <li><b>Airspace someone else holds</b> is asked for with a request. Nothing changes hands until the controller working it accepts.</li>
                        <li><b>Aircraft go with the airspace.</b> When a sector changes hands, the aircraft its old owner was working inside it are handed to the new owner automatically.</li>
                    </ul>
                    <p>Keeping everyone in step is handled by the server. You don't need to do anything to stay in sync.</p>
                </section>

                <section id="installing" class="oz-doc">
                    <p class="oz-kicker">Getting started</p>
                    <h2>Installing</h2>
                    <p>You need vatSys with an Australian or Pacific profile. Install OzServer once, in one place only.</p>
                    <h3>With the vatSys Launcher (recommended)</h3>
                    <ol>
                        <li>Close vatSys.</li>
                        <li>In the vatSys Launcher, open <b>Plugins</b>, choose <code>JoshuaMicallefYBSU/OzServer-Plugin</code>, pick your profile (not <b>All</b>) and press <b>Install</b>.</li>
                        <li>Start vatSys.</li>
                    </ol>
                    <h3>By hand</h3>
                    <ol>
                        <li>Download <code>OzServerPlugin-v&lt;version&gt;.zip</code> from the <a href="https://github.com/JoshuaMicallefYBSU/OzServer-Plugin/releases/latest" class="ui">latest release</a>.</li>
                        <li>Unzip it into <code>Documents\vatSys Files\Profiles\&lt;your profile&gt;\Plugins\OzServer-Plugin\</code>.</li>
                        <li>Restart vatSys.</li>
                    </ol>
                    <div class="oz-note">If Windows has blocked the download, right-click the zip, choose <b>Properties</b> and tick <b>Unblock</b> before unzipping it.</div>
                </section>

                <section id="menus" class="oz-doc">
                    <p class="oz-kicker">Getting started</p>
                    <h2>Where to find it</h2>
                    <p>Once OzServer is loaded, vatSys's menus gain three entries:</p>
                    <table class="oz-table">
                        <thead><tr><th>Menu</th><th>What it does</th></tr></thead>
                        <tbody>
                            <tr><td><span class="ui">Settings</span> &rarr; <span class="ui">Sectors</span></td><td>Opens the OzServer Sectors window. It replaces vatSys's own <span class="ui">Sectors...</span> window while OzServer is running.</td></tr>
                            <tr><td><span class="ui">OzServer How-To</span></td><td>A button in the menu bar that opens this guide inside vatSys.</td></tr>
                            <tr><td><span class="ui">Tools</span> &rarr; <span class="ui">Disable OzServer</span></td><td>Switches OzServer off for the rest of the session. See <a href="#disabling">Turning OzServer off</a>.</td></tr>
                        </tbody>
                    </table>
                    {!! $shot('menu-bar.png', 'The vatSys menu bar with the OzServer How-To button, and Settings opened to show the Sectors entry.') !!}
                </section>

                <section id="logging-on" class="oz-doc">
                    <p class="oz-kicker">Getting started</p>
                    <h2>Logging on</h2>
                    <p>Connect as you normally would. When you log on as a position, its sectors become yours.</p>
                    <ul>
                        <li><b>Someone is covering your position</b> (extending onto it). Your vatSys sends them one request for all of it. Your position moves to you, with its aircraft, once they accept.</li>
                        <li><b>A controller without OzServer is covering your position.</b> Your own position is still yours straight away. Coordinate the handover with them on voice or text.</li>
                    </ul>
                    <div class="oz-note">Once you're logged on, your own position is never released by accident. Only disconnecting gives it up.</div>
                </section>

                <!-- ================= Sectors ================= -->
                <section id="transmitting" class="oz-doc">
                    <p class="oz-kicker">Sectors</p>
                    <h2>Claiming by transmitting</h2>
                    <p>The quickest way to extend is the VSCS panel. Press <b>Transmit</b> on a sector's line:</p>
                    <ul>
                        <li>A <b>sub-sector's</b> line (for example <code>SNO</code>) claims only that sector.</li>
                        <li>A <b>primary's</b> line (for example <code>WOL</code>) claims it and its whole group.</li>
                    </ul>
                    <p>If nobody holds the airspace, it's yours straight away. If someone does, you're asked whether to send them a request. See <a href="#requesting">Asking for a sector</a>.</p>
                    <h3>Letting go</h3>
                    <ul>
                        <li>Switching a line to <b>Idle</b> releases its sector for everyone else.</li>
                        <li>Leaving it on <b>Receive</b> keeps the sector. You're still listening, so the airspace and its aircraft stay yours.</li>
                        <li>Your own logged-on position is never released from the VSCS panel.</li>
                    </ul>
                    <p>OzServer also tidies your VSCS panel. Lines for sub-sectors you now work through their primary's frequency are removed, unless you're transmitting on them or someone else holds them.</p>
                </section>

                <section id="sectors-window" class="oz-doc">
                    <p class="oz-kicker">Sectors</p>
                    <h2>The Sectors window</h2>
                    <p>Open it from <span class="ui">Settings</span> &rarr; <span class="ui">Sectors</span>. It shows what you own, what you're about to change, and the rest of the airspace.</p>
                    {!! $shot('sectors-window.png', 'The OzServer Sectors window: Owned on the left, Planned Changes in the middle, and Available / Controlled on the right.') !!}
                    <table class="oz-table">
                        <thead><tr><th>Part of the window</th><th>Shows</th></tr></thead>
                        <tbody>
                            <tr><td><span class="ui">Owned</span></td><td>The sectors OzServer has as yours. A sector that bundles others is marked <code>*</code>, with its sub-sectors listed under it.</td></tr>
                            <tr><td><span class="ui">Planned Changes</span></td><td>Your requests, other controllers' requests to you, and sectors you're about to drop. Details below.</td></tr>
                            <tr><td><span class="ui">Available</span> / <span class="ui">Controlled</span></td><td>Toggle between the sectors nobody holds and the sectors other controllers hold (with their callsign).</td></tr>
                        </tbody>
                    </table>
                    <p>Sectors are grouped under <b>Flow</b>, <b>Centre</b>, <b>Approach</b>, <b>Tower</b> and <b>Other</b>. Click a row with a <code>&gt;</code> beside it to open or close it.</p>
                    <h3>Making a change</h3>
                    <ol>
                        <li>Select a sector in <span class="ui">Available</span> or <span class="ui">Controlled</span> and press <span class="ui">&lt;&lt;</span> to add it. Or select one in <span class="ui">Owned</span> and press <span class="ui">&gt;&gt;</span> to drop it.</li>
                        <li>The change appears under <span class="ui">Planned Changes</span>. Nothing has been sent yet.</li>
                        <li>Press <span class="ui">Apply Changes</span>. Free sectors are claimed, dropped sectors are released, and anything someone else holds is sent to them as a request. <span class="ui">Cancel</span> throws the planned changes away.</li>
                    </ol>
                    <h3>Planned Changes, and the colours on your scope</h3>
                    <p>While the window is open, the airspace in each list is shaded on your scope in its colour:</p>
                    <table class="oz-table">
                        <thead><tr><th>Heading</th><th>Means</th></tr></thead>
                        <tbody>
                            <tr><td><i class="oz-swatch" style="background:var(--outgoing)"></i><span class="ui">Request Sector Jurisdiction</span></td><td>Sectors you're asking for, or about to ask for. Light green.</td></tr>
                            <tr><td><i class="oz-swatch" style="background:var(--incoming)"></i><span class="ui">Release Sector Jurisdiction</span></td><td>Sectors other controllers are asking you for. Yellow.</td></tr>
                            <tr><td><i class="oz-swatch" style="background:var(--releasing)"></i><span class="ui">Drop Sectors</span></td><td>Sectors you're about to give up when you press <span class="ui">Apply Changes</span>. Red.</td></tr>
                        </tbody>
                    </table>
                </section>

                <section id="requesting" class="oz-doc">
                    <p class="oz-kicker">Sectors</p>
                    <h2>Asking for a sector</h2>
                    <p>When you transmit on, or add, airspace that someone else holds, a <b>Request sector</b> prompt shows what's owned and by whom.</p>
                    {!! $shot('request-prompt.png', 'The Request sector prompt after transmitting on a sector another controller holds.') !!}
                    <ul>
                        <li><span class="ui">Accept</span> sends them a request for it.</li>
                        <li><span class="ui">Reject</span> sends nothing. Any part of the airspace that was free is yours either way.</li>
                    </ul>
                    <p>Your request waits under <span class="ui">Request Sector Jurisdiction</span> until they answer. If they reject it, you're told. To withdraw it, select it and press <span class="ui">Cancel Request</span>.</p>
                    <div class="oz-note">OzServer never sends a request without you agreeing to it first.</div>
                </section>

                <section id="answering" class="oz-doc">
                    <p class="oz-kicker">Sectors</p>
                    <h2>Answering a request</h2>
                    <p>When another controller asks for your airspace:</p>
                    <ul>
                        <li>a tone plays;</li>
                        <li>the <span class="ui">Settings</span> menu flashes, and <span class="ui">Sectors</span> shows a count, such as <code>[1]</code>;</li>
                        <li>the Sectors window opens, with the requested airspace shaded <i class="oz-swatch" style="background:var(--incoming)"></i>yellow on your scope.</li>
                    </ul>
                    <p>Answer it when you're ready. Nothing is put over your traffic.</p>
                    {!! $shot('incoming-request.png', 'An incoming request selected under Release Sector Jurisdiction, with Accept and Reject below it.') !!}
                    <ol>
                        <li>Select the request under <span class="ui">Release Sector Jurisdiction</span>. To answer every request at once, select the heading itself.</li>
                        <li>Press <span class="ui">Accept</span> to hand the airspace over, or <span class="ui">Reject</span> to keep it.</li>
                    </ol>
                    <p>Accepting hands over the sector <b>and the aircraft you're working inside it</b>. Coordinate anything that needs saying first, just as you would for a normal handoff.</p>
                </section>

                <section id="releasing" class="oz-doc">
                    <p class="oz-kicker">Sectors</p>
                    <h2>Releasing sectors</h2>
                    <p>To give up airspace you no longer want, either:</p>
                    <ul>
                        <li>switch its VSCS line to <b>Idle</b>, or</li>
                        <li>in the Sectors window, select it under <span class="ui">Owned</span>, press <span class="ui">&gt;&gt;</span>, then <span class="ui">Apply Changes</span>.</li>
                    </ul>
                    <p>The sector is then free for another controller to take.</p>
                    <div class="oz-note warn">Releasing airspace doesn't hand its aircraft to anyone. Hand them off before you release it, as usual.</div>
                </section>

                <section id="extension-limits" class="oz-doc">
                    <p class="oz-kicker">Sectors</p>
                    <h2>Extension limits</h2>
                    <p>OzServer applies VATPAC's ATS Policy on enroute extensions:</p>
                    <table class="oz-table">
                        <thead><tr><th>Your hours</th><th>You can work</th></tr></thead>
                        <tbody>
                            <tr><td>Under 150 hours at C1 and above</td><td>Your own position plus <b>4</b> enroute sectors</td></tr>
                            <tr><td>150 hours or more at C1 and above</td><td>Your own position plus <b>6</b> enroute sectors</td></tr>
                        </tbody>
                    </table>
                    <p>Going past your limit (by transmitting, from the Sectors window, or by request) shows a <b>Maximum sectors reached</b> notice, and the extra sector isn't added. Nothing you already hold is taken away. Logging on, reconnecting and accepting a handover are never limited.</p>
                </section>

                <!-- ================= Traffic ================= -->
                <section id="tags" class="oz-doc">
                    <p class="oz-kicker">Traffic</p>
                    <h2>Aircraft and tags</h2>
                    <ul>
                        <li><b>Tags follow sectors.</b> When a sector changes hands, the aircraft its old owner was working inside it arrive under the new owner's jurisdiction. Their cleared level, scratchpad and label data come with them.</li>
                        <li><b>Preview before you take it.</b> While a sector is planned or requested, the aircraft that would come with it are drawn as ghost jurisdiction on your scope.</li>
                        <li><b>Normal handoffs are unchanged.</b> Handing an aircraft to the next sector works exactly as it does in vatSys.</li>
                        <li><b>Everyone sees the same flight data.</b> Flight plans, positions and label data for the aircraft you're working are shared, so other scopes and the live map agree with yours.</li>
                        <li><b>Coupling help.</b> If vatSys won't couple an activated flight plan to its radar track, OzServer does it for you.</li>
                    </ul>
                </section>

                <section id="arrival-list" class="oz-doc">
                    <p class="oz-kicker">Traffic</p>
                    <h2>Arrival List</h2>
                    <p>The Arrival List (for example <code>A:YSSY List</code>) is shared. Every controller sees the same flights in the same order: every departed flight inbound, with its landing time, type and global ops.</p>
                    {!! $shot('arrival-list.png', 'The shared Arrival List for YSSY, with the viewer and FMP badges above the list.') !!}
                    <ul>
                        <li><b>Reordering:</b> anyone can reorder the list, unless the airport's FMP (<code>SY_FMP</code> for YSSY) is online. Then only the FMP can.</li>
                        <li><b>Badges above the list</b> show how many controllers have it open (hover over it to see who) and whether the FMP is online.</li>
                        <li><b>Global ops</b> can be edited from the list by any controller, up to 15 characters.</li>
                    </ul>
                </section>

                <section id="atis" class="oz-doc">
                    <p class="oz-kicker">Traffic</p>
                    <h2>ATIS</h2>
                    <p>There's nothing to do. Your ATIS, whether vatSys's own or one broadcast through ATIS Plugin, is published as it changes and shown on the <a href="/live-map" target="_blank" rel="noopener">live map</a>. This happens on the live network only, never on SweatBox.</p>
                </section>

                <!-- ================= Other controllers ================= -->
                <section id="no-plugin" class="oz-doc">
                    <p class="oz-kicker">Other controllers</p>
                    <h2>Controllers without OzServer</h2>
                    <p>A controller connected without the plugin still holds their airspace. That includes their position, what their controller info says they cover (for example "Extending MUN, GUN"), and the sectors their frequencies stand for.</p>
                    <ul>
                        <li>Their sectors show under <span class="ui">Controlled</span>, marked <b>no plugin</b>, and are tinted light orange on the live map.</li>
                        <li>They can't answer a request, so <b>coordinate on voice or text</b>. Tag handoffs with them are manual, as in plain vatSys.</li>
                        <li>Once they've handed a sector to you, or logged off, transmit on it or add it in the Sectors window. You'll be asked whether to take it: choose <span class="ui">Take Sectors</span>.</li>
                    </ul>
                    {!! $shot('take-sectors.png', 'The Take Sectors / Leave prompt for airspace held by a controller without OzServer.') !!}
                    <div class="oz-note warn">Only take their airspace once they've handed it over or logged off. OzServer doesn't check this, so it's up to you.</div>
                </section>

                <section id="disconnects" class="oz-doc">
                    <p class="oz-kicker">Other controllers</p>
                    <h2>Disconnects and reconnects</h2>
                    <table class="oz-table">
                        <thead><tr><th>What happened</th><th>What happens to the airspace</th></tr></thead>
                        <tbody>
                            <tr><td>You closed vatSys or pressed <b>Disconnect</b></td><td>Your sectors are released straight away. Your tags are left with nobody.</td></tr>
                            <tr><td>vatSys crashed or your connection dropped</td><td>Your sectors are kept for you for <b>2 minutes</b>, unless someone else takes them.</td></tr>
                            <tr><td>You reconnected within 2 minutes</td><td>Your sectors, VSCS transmit lines, tags and traffic come back, except anything someone has taken since.</td></tr>
                            <tr><td>Someone logged on as your position within 2 minutes</td><td>They take over your position and your tags inside it. Sectors you were extending onto, and their tags, aren't included.</td></tr>
                            <tr><td>2 minutes passed</td><td>Your sectors go to whoever covers the position above, or become available.</td></tr>
                        </tbody>
                    </table>
                    <h3>Covering for a controller who dropped</h3>
                    <p>While a dropped controller's airspace is being kept for them, you can still take it if needed. When you transmit on it or add it, you're told whose it is and how long it's kept for. Choose <span class="ui">Take Sectors</span> to take it now, or <span class="ui">Leave</span> to give them the chance to come back.</p>
                    <div class="oz-note">An untracked tag is still shown on everyone's scope, but it's never pulled into anyone's jurisdiction by itself. Whoever now holds the airspace picks it up.</div>
                </section>

                <section id="roles" class="oz-doc">
                    <p class="oz-kicker">Other controllers</p>
                    <h2>Observers, FMP and SweatBox</h2>
                    <ul>
                        <li><b>Observers</b> never claim anything. You see the airspace of the controller you're watching, live.</li>
                        <li><b>Flow (FMP) positions</b> have labels for aircraft outside their jurisdiction hidden automatically, and relief cover is marked as such for the controller handing over.</li>
                        <li><b>Live and SweatBox are kept apart.</b> The live network, SweatBox-1 and SweatBox-2 each have their own sector ownership, so training never touches live airspace.</li>
                        <li><b>Messages from the OzServer team</b> occasionally appear in a popup, for example to ask about a problem you've reported. Reply from the popup if it asks you to.</li>
                    </ul>
                </section>

                <!-- ================= Help ================= -->
                <section id="updates" class="oz-doc">
                    <p class="oz-kicker">Help</p>
                    <h2>Updates</h2>
                    <p>OzServer keeps itself up to date. It checks when vatSys opens and every few minutes after that, and never interrupts a session.</p>
                    <p>When a new version has been downloaded, an <b>OzServer update</b> window tells you. Press <span class="ui">Restart</span> to close and reopen vatSys with the new version. Your frequencies, sectors and tags are restored when you reconnect. Or close the window and the update applies the next time you start vatSys.</p>
                    {!! $shot('update-window.png', 'The OzServer update window with its Restart button.') !!}
                </section>

                <section id="disabling" class="oz-doc">
                    <p class="oz-kicker">Help</p>
                    <h2>Turning OzServer off</h2>
                    <p>If something is going wrong mid-session, <span class="ui">Tools</span> &rarr; <span class="ui">Disable OzServer</span> puts you back on plain vatSys straight away:</p>
                    <ul>
                        <li>your sectors are released;</li>
                        <li>OzServer stops changing anything in vatSys;</li>
                        <li>vatSys's own <span class="ui">Sectors...</span> window comes back.</li>
                    </ul>
                    <p>It lasts until you restart vatSys.</p>
                </section>

                <section id="troubleshooting" class="oz-doc">
                    <p class="oz-kicker">Help</p>
                    <h2>Troubleshooting</h2>
                    <table class="oz-table">
                        <thead><tr><th>Problem</th><th>Try</th></tr></thead>
                        <tbody>
                            <tr><td>No <span class="ui">Sectors</span> entry under Settings</td><td>Check the plugin is in your profile's <code>Plugins\OzServer-Plugin</code> folder and isn't blocked by Windows. Then restart vatSys.</td></tr>
                            <tr><td>"Installed more than once" in the Errors window</td><td>vatSys found two copies and is using one. Close vatSys, delete the copy the message says is ignored, and restart.</td></tr>
                            <tr><td>Sectors aren't syncing</td><td>Check you're connected to the live network or a SweatBox server, and that OzServer hasn't been disabled this session. Look in the Errors window for a connection message.</td></tr>
                            <tr><td>A sector you expected is taken</td><td>Switch to <span class="ui">Controlled</span> in the Sectors window to see who holds it, then send a request.</td></tr>
                            <tr><td>Anything badly wrong mid-session</td><td><span class="ui">Tools</span> &rarr; <span class="ui">Disable OzServer</span>.</td></tr>
                        </tbody>
                    </table>
                    <h3>Reporting a problem</h3>
                    <p>Open an issue on <a href="https://github.com/JoshuaMicallefYBSU/OzServer-Plugin/issues/new/choose" target="_blank" rel="noopener">GitHub</a> and attach your log. You'll find it at <code>Documents\vatSys Files\ozserver_&lt;date&gt;.txt</code>. If another controller was involved, say what they were holding and doing: most problems involve two controllers.</p>
                </section>
            </main>
        </div>

        <footer>
            <div class="wrap">
                <span class="foot-brand">
                    @include('partials.mark', ['ring' => 'currentColor', 'held' => 'var(--accent)'])
                    OzServer &mdash; &copy; Joshua Micallef | 2026{{ date('Y') > 2026 ? '-'.date('Y') : '' }}
                </span>
                <a href="/">Home</a>
                <a href="/live-map">Live map</a>
                <a href="https://github.com/JoshuaMicallefYBSU/OzServer-Plugin">GitHub</a>
            </div>
        </footer>

        <dialog class="oz-zoom" id="oz-zoom"><img alt=""></dialog>

        <script>
            (function () {
                var links = Array.prototype.slice.call(document.querySelectorAll('.oz-side a[href^="#"]'));
                var details = document.getElementById('oz-side-details');
                var narrow = window.matchMedia('(max-width: 900px)');

                // Wide: the side navigation is always open. Narrow: it folds away, and closes again
                // once a section has been picked.
                function fitNavigation() { details.open = !narrow.matches; }
                fitNavigation();
                if (narrow.addEventListener) narrow.addEventListener('change', fitNavigation); else narrow.addListener(fitNavigation);
                links.forEach(function (link) {
                    link.addEventListener('click', function () { if (narrow.matches) details.open = false; });
                });

                // Highlights the section being read: the last one whose heading has scrolled past
                // the top bar.
                var sections = links.map(function (link) { return document.getElementById(link.getAttribute('href').slice(1)); });
                function markActive() {
                    var offset = 100, current = sections[0];
                    sections.forEach(function (section) { if (section && section.getBoundingClientRect().top - offset <= 0) current = section; });
                    if (window.innerHeight + window.scrollY >= document.body.scrollHeight - 4) current = sections[sections.length - 1];
                    links.forEach(function (link, i) { link.classList.toggle('active', sections[i] === current); });
                }
                window.addEventListener('scroll', markActive, { passive: true });
                window.addEventListener('resize', markActive);
                markActive();

                // Screenshots open full size.
                var zoom = document.getElementById('oz-zoom');
                var zoomImg = zoom.querySelector('img');
                document.querySelectorAll('[data-zoom]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (typeof zoom.showModal !== 'function') { window.open(button.getAttribute('data-zoom'), '_blank'); return; }
                        zoomImg.src = button.getAttribute('data-zoom');
                        zoomImg.alt = button.querySelector('img').alt;
                        zoom.showModal();
                    });
                });
                zoom.addEventListener('click', function () { zoom.close(); });
            })();
        </script>
    </body>
</html>
