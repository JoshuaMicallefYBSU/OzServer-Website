<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>OzServer | Console</title>
        @include('partials.icons')
        @include('partials.fonts')
        <style>
            /* Same palette and panels as /live-map, so the two read as one tool. */
            :root {
                --bg: rgba(8, 13, 24, 0.92);
                --panel: rgba(14, 22, 38, 0.92);
                --line: rgba(150, 172, 225, 0.16);
                --text: #EEF2FB;
                --muted: #9AA6C2;
                --faint: #66728F;
                --accent: #7FA2FF;
                --warn: #fb923c;
                --bad: #f87171;
                --good: #4ade80;
                --tag: #facc15;
                --flow: #c4b5fd;
                --mono: "Geist Mono", ui-monospace, "Cascadia Mono", Consolas, monospace;
                --sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            }
            * { box-sizing: border-box; }
            html, body { margin: 0; height: 100%; background: #060A13; color: var(--text); font-family: var(--sans); font-size: 13.5px; -webkit-font-smoothing: antialiased; }
            button, input, select, textarea { font: inherit; color: inherit; }
            [hidden] { display: none !important; }

            .oz-panel { background: var(--bg); border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 12px 34px rgba(0, 0, 0, 0.4); }

            /* ---------- gate ---------- */
            .oz-gate { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: #060A13; z-index: 10; }
            .oz-gate-box { padding: 32px; width: 300px; text-align: center; }
            .oz-gate-box h1 { display: flex; align-items: center; justify-content: center; gap: 9px; font-size: 17px; font-weight: 600; letter-spacing: -0.01em; margin: 0 0 18px; }
            .oz-gate-box h1 svg, .oz-brand svg { width: 22px; height: 22px; }
            .oz-gate-box h1 span, .oz-brand span { color: var(--muted); font-weight: 400; }
            .oz-gate-box input { width: 100%; margin-bottom: 12px; }
            .oz-gate-box button { width: 100%; }
            .oz-gate-error { color: var(--bad); font-size: 12px; margin: 10px 0 0; }

            /* ---------- shared controls ---------- */
            .oz-input, .oz-gate-box input, select.oz-input, textarea.oz-input {
                background: #0E1626; border: 1px solid rgba(255, 255, 255, 0.14); border-radius: 6px; padding: 5px 8px; color: var(--text);
            }
            .oz-btn, .oz-gate-box button {
                background: #0E1626; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; padding: 6px 11px;
                cursor: pointer; white-space: nowrap; font-size: 12.5px;
            }
            .oz-btn:hover { border-color: rgba(255, 255, 255, 0.35); }
            .oz-btn.primary, .oz-gate-box button { background: #EEF2FB; color: #070B14; border-color: #EEF2FB; font-weight: 500; }
            .oz-btn.primary:disabled { opacity: 0.5; cursor: default; }
            .oz-btn.on { background: rgba(91, 140, 255, 0.15); border-color: rgba(91, 140, 255, 0.6); color: var(--accent); }
            .oz-btn.danger { color: var(--bad); border-color: rgba(248, 113, 113, 0.4); }
            .oz-badge {
                display: inline-block; font-size: 10.5px; font-weight: 700;
                padding: 1px 5px; border-radius: 4px; background: rgba(255, 255, 255, 0.08); color: var(--muted); vertical-align: 1px;
            }
            .oz-badge.good { background: rgba(74, 222, 128, 0.15); color: var(--good); }
            .oz-badge.warn { background: rgba(251, 146, 60, 0.18); color: var(--warn); }
            .oz-badge.bad { background: rgba(248, 113, 113, 0.18); color: var(--bad); }
            .oz-badge.accent { background: rgba(91, 140, 255, 0.15); color: var(--accent); }
            .oz-badge.srv-live { background: rgba(74, 222, 128, 0.15); color: var(--good); }
            .oz-badge.srv-sb1 { background: rgba(250, 204, 21, 0.15); color: var(--tag); }
            .oz-badge.srv-sb2 { background: rgba(167, 139, 250, 0.15); color: var(--flow); }
            .oz-chip {
                display: inline-flex; align-items: center; gap: 4px; margin: 2px 3px 2px 0; padding: 1px 7px; border-radius: 999px;
                border: 1px solid rgba(255, 255, 255, 0.14); background: rgba(255, 255, 255, 0.04); font-family: var(--mono); font-size: 11px;
            }
            .oz-section-title { margin: 12px 2px 6px; font-size: 11.5px; color: var(--muted); }
            .oz-empty { color: var(--faint); padding: 14px 6px; }

            /* ---------- layout ---------- */
            #app { position: fixed; inset: 0; display: grid; grid-template-rows: 48px 1fr; gap: 10px; padding: 10px; }
            .oz-topbar { display: flex; align-items: center; gap: 16px; padding: 0 14px; min-width: 0; }
            .oz-brand { display: inline-flex; align-items: center; gap: 9px; font-weight: 600; font-size: 16px; letter-spacing: -0.01em; white-space: nowrap; color: var(--text); text-decoration: none; }
            .oz-stats { display: flex; gap: 16px; flex: 1; overflow: hidden; }
            .oz-stat { display: flex; flex-direction: column; line-height: 1.1; white-space: nowrap; }
            .oz-stat b { font-size: 16px; font-weight: 500; font-variant-numeric: tabular-nums; }
            .oz-stat small { font-size: 11.5px; color: var(--muted); }
            .oz-stat.bad b { color: var(--bad); }
            .oz-stat.warn b { color: var(--warn); }
            .oz-live { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: 11px; white-space: nowrap; }
            .oz-live i { width: 8px; height: 8px; border-radius: 50%; background: var(--faint); display: inline-block; }
            .oz-live.on i { background: var(--good); box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.2); }

            .oz-body { display: grid; grid-template-columns: 290px minmax(0, 1fr) 380px; gap: 10px; min-height: 0; }
            .oz-left, .oz-right { display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
            .oz-center { display: grid; grid-template-rows: var(--map-h, 38%) minmax(0, 1fr); gap: 10px; min-height: 0; }
            .oz-center.map-collapsed { --map-h: 40px; }

            /* ---------- connected controllers ---------- */
            .oz-left-head { padding: 10px 10px 0; }
            .oz-left-head .oz-input { width: 100%; }
            .oz-list { flex: 1; overflow-y: auto; padding: 4px 8px 8px; }
            .oz-ctl {
                display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; background: none;
                border: 1px solid transparent; border-radius: 8px; padding: 7px 8px; cursor: pointer;
            }
            .oz-ctl:hover { background: rgba(255, 255, 255, 0.04); }
            .oz-ctl.selected { background: rgba(91, 140, 255, 0.08); border-color: rgba(91, 140, 255, 0.35); }
            .oz-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: var(--good); }
            .oz-dot.stale { background: var(--warn); }
            .oz-dot.off { background: var(--faint); }
            .oz-ctl .main { flex: 1; min-width: 0; }
            .oz-ctl .title { font-family: var(--mono); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .oz-ctl .sub { color: var(--muted); font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .oz-ctl .right { text-align: right; font-family: var(--mono); font-size: 11px; color: var(--muted); white-space: nowrap; }
            .oz-ctl .right .errs { color: var(--bad); }
            .oz-left-foot { border-top: 1px solid var(--line); padding: 8px 10px; color: var(--faint); font-size: 11.5px; display: flex; gap: 10px; flex-wrap: wrap; }
            .oz-left-foot span { display: inline-flex; align-items: center; gap: 4px; }

            /* ---------- map ---------- */
            .oz-mapwrap { display: flex; flex-direction: column; overflow: hidden; }
            .oz-panel-head { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-bottom: 1px solid var(--line); }
            .oz-panel-head h2 { margin: 0; font-size: 12.5px; color: var(--muted); font-weight: 500; }
            .oz-panel-head .spacer { flex: 1; }
            .oz-panel-head a { color: var(--accent); font-size: 11px; text-decoration: none; }
            .oz-mapwrap iframe { flex: 1; width: 100%; border: 0; background: #060A13; }
            .map-collapsed .oz-mapwrap iframe { display: none; }

            /* ---------- activity ---------- */
            .oz-feedwrap { display: flex; flex-direction: column; overflow: hidden; }
            .oz-feedbar { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 8px 10px; border-bottom: 1px solid var(--line); }
            .oz-toggle {
                display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; font-size: 11px; cursor: pointer;
                border: 1px solid rgba(255, 255, 255, 0.14); background: transparent; color: var(--faint);
            }
            .oz-toggle i { width: 8px; height: 8px; border-radius: 50%; background: var(--c); opacity: 0.4; }
            .oz-toggle.on { color: var(--text); border-color: color-mix(in srgb, var(--c) 55%, transparent); background: color-mix(in srgb, var(--c) 14%, transparent); }
            .oz-toggle.on i { opacity: 1; }
            .oz-toggle .n { font-family: var(--mono); color: var(--muted); }
            .oz-feedbar .spacer { flex: 1; }
            .oz-feedbar .oz-input { padding: 3px 7px; font-size: 12px; }
            .oz-focus { display: inline-flex; align-items: center; gap: 6px; padding: 2px 4px 2px 9px; border-radius: 999px; background: rgba(91, 140, 255, 0.15); color: var(--accent); font-family: var(--mono); font-size: 11px; }
            .oz-focus button { background: none; border: 0; color: var(--accent); cursor: pointer; padding: 0 4px; }

            #feed { flex: 1; overflow-y: auto; font-size: 12px; }
            .oz-ev { display: grid; grid-template-columns: 74px 18px 1fr; gap: 8px; padding: 5px 10px; border-bottom: 1px solid var(--line); cursor: pointer; line-height: 1.4; }
            .oz-ev:hover { background: rgba(255, 255, 255, 0.025); }
            .oz-ev time { font-family: var(--mono); font-size: 11px; color: var(--faint); padding-top: 1px; }
            .oz-ev .icon { text-align: center; color: var(--c); }
            .oz-ev .who { font-family: var(--mono); font-weight: 600; color: #A9C1FF; margin-right: 6px; }
            .oz-ev .who.api { color: var(--muted); }
            .oz-ev .msg { color: var(--text); word-break: break-word; }
            .oz-ev.g-errors { background: rgba(248, 113, 113, 0.05); }
            .oz-ev.g-errors .msg { color: #fecaca; }
            .oz-ev .facts { margin-top: 2px; }
            .oz-ev pre { grid-column: 3; margin: 6px 0 2px; padding: 8px; border-radius: 6px; background: #0A1120; color: var(--muted); font-family: var(--mono); font-size: 11px; white-space: pre-wrap; word-break: break-word; }
            .oz-raw { padding: 2px 10px; font-family: var(--mono); font-size: 11px; white-space: pre-wrap; word-break: break-word; border-bottom: 1px solid rgba(255, 255, 255, 0.03); color: var(--muted); }
            .oz-raw b { color: var(--text); font-weight: normal; }
            .oz-raw .e { color: var(--bad); }

            /* ---------- right: details / messages ---------- */
            .oz-tabs { display: flex; border-bottom: 1px solid var(--line); }
            .oz-tabs button {
                flex: 1; background: none; border: 0; padding: 11px 4px; cursor: pointer; color: var(--muted);
                font-size: 12.5px; font-weight: 500; border-bottom: 2px solid transparent;
            }
            .oz-tabs button.active { color: var(--text); border-bottom-color: var(--text); }
            .oz-tab-body { flex: 1; overflow-y: auto; padding: 10px 12px 14px; }
            .oz-detail h3 { margin: 0; font-size: 18px; font-weight: 500; font-family: var(--mono); }
            .oz-kind { font-size: 11.5px; color: var(--muted); }
            .oz-kv { display: grid; grid-template-columns: 100px 1fr; gap: 4px 10px; margin-top: 10px; }
            .oz-kv dt { color: var(--muted); }
            .oz-kv dd { margin: 0; font-family: var(--mono); word-break: break-word; }
            .oz-mini { padding: 4px 0; border-top: 1px solid var(--line); font-size: 12px; }
            .oz-mini time { font-family: var(--mono); color: var(--faint); font-size: 11px; margin-right: 6px; }
            .oz-actions { display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap; }
            .oz-summary { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 6px; }
            .oz-summary div { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; }
            .oz-summary b { display: block; font-size: 18px; font-family: var(--mono); }
            .oz-summary small { color: var(--muted); font-size: 11.5px; }
            .oz-toprow { display: flex; gap: 8px; padding: 4px 0; border-top: 1px solid var(--line); font-size: 12px; }
            .oz-toprow b { font-family: var(--mono); color: var(--bad); min-width: 26px; }

            .oz-compose { display: flex; flex-direction: column; gap: 9px; }
            .oz-compose label.l { color: var(--muted); font-size: 11.5px; display: block; margin-bottom: 3px; }
            .oz-compose .oz-input { width: 100%; }
            .oz-compose textarea { min-height: 110px; resize: vertical; }
            .oz-compose .row { display: flex; gap: 8px; }
            .oz-compose .row > div { flex: 1; min-width: 0; }
            .oz-check { display: flex; align-items: center; gap: 6px; font-size: 12px; }
            .oz-hint { color: var(--faint); font-size: 11px; }
            .oz-status { font-size: 12px; min-height: 16px; }
            .oz-status.ok { color: var(--good); }
            .oz-status.err { color: var(--bad); }
            .oz-picker { position: relative; }
            .oz-picker > button { width: 100%; text-align: left; display: flex; justify-content: space-between; }
            .oz-picker-list {
                position: absolute; z-index: 5; top: calc(100% + 4px); left: 0; right: 0; max-height: 240px; overflow-y: auto;
                background: #0A1120; border: 1px solid rgba(255, 255, 255, 0.18); border-radius: 8px; padding: 6px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            }
            .oz-picker-list label { display: flex; align-items: center; gap: 8px; padding: 4px 6px; border-radius: 6px; cursor: pointer; font-family: var(--mono); font-size: 12px; }
            .oz-picker-list label:hover { background: rgba(255, 255, 255, 0.05); }
            .oz-picker-list .all { font-family: var(--sans); border-bottom: 1px solid var(--line); margin-bottom: 4px; padding-bottom: 6px; }

            .oz-msg { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; font-size: 12px; margin-top: 8px; }
            .oz-msg.dim { opacity: 0.55; }
            .oz-msg .head { display: flex; align-items: baseline; gap: 6px; }
            .oz-msg .head b { font-size: 13px; }
            .oz-msg .head .oz-btn { margin-left: auto; padding: 1px 7px; font-size: 11px; }
            .oz-msg .meta { color: var(--faint); font-size: 11px; margin: 3px 0 6px; }
            .oz-msg .body { white-space: pre-wrap; word-break: break-word; color: #cbd5e1; }
            .oz-msg .stats { margin-top: 6px; color: var(--muted); font-size: 11px; cursor: pointer; }
            .oz-msg .receipts { margin-top: 4px; color: var(--faint); font-size: 11px; font-family: var(--mono); }
            .oz-msg .reply { margin-top: 6px; border-left: 2px solid var(--accent); padding: 3px 0 3px 9px; }
            .oz-msg .reply .who { color: #A9C1FF; font-size: 11px; }
            .oz-msg .reply .text { white-space: pre-wrap; word-break: break-word; }

            @media (max-width: 1200px) {
                .oz-body { grid-template-columns: 240px minmax(0, 1fr); }
                .oz-right { grid-column: 1 / -1; max-height: 420px; }
            }
        </style>
    </head>
    <body>
        <div id="gate" class="oz-gate" @if ($unlocked) hidden @endif>
            <div class="oz-panel oz-gate-box">
                <h1>@include('partials.mark')OzServer <span>Console</span></h1>
                <input type="password" id="gate-password" placeholder="Password" autocomplete="off">
                <button id="gate-submit">Enter</button>
                <p id="gate-error" class="oz-gate-error" hidden>Incorrect password.</p>
            </div>
        </div>

        <div id="app" @if (! $unlocked) hidden @endif>
            <header class="oz-panel oz-topbar">
                <a class="oz-brand" href="/" title="OzServer home">@include('partials.mark')OzServer <span>Console</span></a>
                <select id="server" class="oz-input" aria-label="Server">
                    <option value="all">All servers</option>
                    <option value="live">Live VATSIM</option>
                    <option value="sb1">SweatBox 1</option>
                    <option value="sb2">SweatBox 2</option>
                </select>
                <div class="oz-stats" id="stats"></div>
                <div class="oz-live" id="live" title="Log feed"><i></i><span>Connecting…</span></div>
                <button class="oz-btn" id="lock">Lock</button>
            </header>

            <div class="oz-body">
                <aside class="oz-panel oz-left">
                    <div class="oz-left-head">
                        <div class="oz-section-title" style="margin-top:2px">Connected controllers</div>
                        <input class="oz-input" id="ctl-search" placeholder="Filter callsigns…">
                    </div>
                    <div class="oz-list" id="controllers"><div class="oz-empty">Loading…</div></div>
                    <div class="oz-left-foot">
                        <span><i class="oz-dot"></i>Calling now</span>
                        <span><i class="oz-dot stale"></i>Quiet &gt;30s</span>
                        <span><i class="oz-dot off"></i>Seen in logs only</span>
                    </div>
                </aside>

                <section class="oz-center" id="center">
                    <div class="oz-panel oz-mapwrap">
                        <div class="oz-panel-head">
                            <h2>Live map</h2>
                            <span class="spacer"></span>
                            <a id="map-open" href="/live-map" target="_blank" rel="noopener">Open full map ↗</a>
                            <button class="oz-btn" id="map-toggle">Hide</button>
                        </div>
                        <iframe id="map" title="Live map" loading="lazy"></iframe>
                    </div>

                    <div class="oz-panel oz-feedwrap">
                        <div class="oz-feedbar" id="feedbar">
                            <span id="groups"></span>
                            <span class="spacer"></span>
                            <span class="oz-focus" id="focus" hidden><span id="focus-name"></span><button id="focus-clear" title="Show everyone">✕</button></span>
                            <input class="oz-input" id="search" placeholder="Search text…" style="width:150px">
                            <select class="oz-input" id="window" title="History loaded">
                                <option value="15 minutes">15 min</option>
                                <option value="1 hour" selected>1 hour</option>
                                <option value="6 hours">6 hours</option>
                                <option value="1 day">1 day</option>
                            </select>
                            <button class="oz-btn" id="view" title="Readable, or exactly what was recorded">Raw</button>
                            <button class="oz-btn" id="pause">Pause</button>
                            <button class="oz-btn" id="clear">Clear</button>
                        </div>
                        <div id="feed"><div class="oz-empty">Waiting for activity…</div></div>
                    </div>
                </section>

                <aside class="oz-panel oz-right">
                    <nav class="oz-tabs">
                        <button data-tab="details" class="active">Details</button>
                        <button data-tab="messages" @if (! $messagingEnabled) hidden @endif>Messages</button>
                    </nav>
                    <div class="oz-tab-body oz-detail" data-body="details" id="details"></div>
                    <div class="oz-tab-body" data-body="messages" hidden>
                        <form class="oz-compose" id="compose" autocomplete="off">
                            <div class="oz-hint">Pops up in each controller's vatSys with a sound, signed <b>OzServer Development Team</b>.</div>
                            <div class="row">
                                <div>
                                    <label class="l">Server</label>
                                    <select class="oz-input" id="m-server">
                                        <option value="all">All servers</option>
                                        <option value="live" selected>Live VATSIM</option>
                                        <option value="sb1">SweatBox 1</option>
                                        <option value="sb2">SweatBox 2</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="l">To</label>
                                    <div class="oz-picker" id="picker">
                                        <button type="button" class="oz-input" id="picker-btn"><span id="picker-label">Everyone</span><span>▾</span></button>
                                        <div class="oz-picker-list" id="picker-list" hidden></div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="l" for="m-title">Title (optional)</label>
                                <input class="oz-input" id="m-title" maxlength="120">
                            </div>
                            <div>
                                <label class="l" for="m-body">Message</label>
                                <textarea class="oz-input" id="m-body" maxlength="2000" required></textarea>
                            </div>
                            <div id="expires-row">
                                <label class="l" for="m-expires">Show for</label>
                                <select class="oz-input" id="m-expires">
                                    <option value="15" selected>15 minutes - and anyone connecting meanwhile</option>
                                    <option value="60">1 hour</option>
                                    <option value="120">2 hours</option>
                                    <option value="360">6 hours</option>
                                    <option value="1440">1 day</option>
                                    <option value="10080">1 week</option>
                                </select>
                            </div>
                            <div class="oz-hint" id="targeted-note" hidden>Goes to the callsigns ticked, on the server each is on, for the next 24 hours: whoever is logged on as one of them in that time gets it, and nobody else.</div>
                            <label class="oz-check"><input type="checkbox" id="m-reply" checked> Let controllers reply</label>
                            <button type="submit" class="oz-btn primary" id="m-send">Send</button>
                            <div class="oz-status" id="m-status"></div>
                        </form>
                        <div class="oz-section-title" style="margin-top:18px">Sent - last 7 days</div>
                        <div id="sent"><div class="oz-empty">Loading…</div></div>
                    </div>
                </aside>
            </div>
        </div>

        <script>
            const apiBaseUrl = @json(rtrim(config('services.ozserver_api.url'), '/'));
            const INITIALLY_UNLOCKED = @json($unlocked);
            const MESSAGING_ENABLED = @json($messagingEnabled);
            const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
            const SERVERS = ['live', 'sb1', 'sb2'];

            const LOG_POLL_MS = 2000;      // the feed's tail
            const STATE_POLL_MS = 10000;   // connected controllers, sector ownership, sent messages
            const MAX_EVENTS = 4000;

            // Every value shown came from a plugin or a person, so all of it is escaped - quotes
            // included, since some of it goes into attributes.
            const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const hhmmss = iso => new Date(iso).toISOString().slice(11, 19) + 'z';
            const stamp = iso => iso ? new Date(iso).toISOString().slice(0, 16).replace('T', ' ') + 'z' : '';
            const ago = iso => {
                if (!iso) return '';
                const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
                return s < 60 ? `${s}s` : s < 3600 ? `${Math.round(s / 60)}m` : `${Math.round(s / 3600)}h`;
            };

            // This site's own /console routes: JSON both ways, CSRF token, session cookie.
            async function consoleFetch(path, options = {}) {
                const response = await fetch(path, {
                    ...options,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, ...(options.headers || {}) },
                });
                let body = null;
                try { body = await response.json(); } catch { body = null; }
                return { ok: response.ok, status: response.status, body };
            }

            // ---------------------------------------------------------------------------------
            // Gate: the password is checked on the server and remembered in the session.
            // ---------------------------------------------------------------------------------
            const gate = document.getElementById('gate');
            const app = document.getElementById('app');
            let started = false;

            function unlock() {
                gate.hidden = true;
                app.hidden = false;
                if (!started) { started = true; start(); }
            }

            async function tryUnlock() {
                const password = document.getElementById('gate-password').value;
                const result = await consoleFetch('/console/unlock', { method: 'POST', body: JSON.stringify({ password }) });
                if (result.ok) return unlock();
                // The page sat open until its session ran out: a fresh one, not "wrong password".
                if (result.status === 419) return window.location.reload();
                const error = document.getElementById('gate-error');
                error.textContent = result.status === 429 ? 'Too many attempts - wait a minute.' : 'Incorrect password.';
                error.hidden = false;
            }
            document.getElementById('gate-submit').addEventListener('click', tryUnlock);
            document.getElementById('gate-password').addEventListener('keydown', e => { if (e.key === 'Enter') tryUnlock(); });
            document.getElementById('lock').addEventListener('click', async () => {
                await consoleFetch('/console/lock', { method: 'POST' });
                window.location.reload();
            });

            // ---------------------------------------------------------------------------------
            // What a log line is about. Each plugin line has a category; the API's own lines
            // (plugin_version "server") are ServerSector / ServerFDR. context.action refines it.
            // ---------------------------------------------------------------------------------
            const GROUPS = [
                { id: 'errors', label: 'Errors', colour: '#f87171', icon: '⚠', on: true },
                { id: 'sectors', label: 'Sector transfers', colour: '#7FA2FF', icon: '▦', on: true },
                { id: 'tags', label: 'Tags & handoffs', colour: '#facc15', icon: '✈', on: true },
                { id: 'coupling', label: 'Coupling', colour: '#a3a3a3', icon: '⌁', on: false },
                { id: 'connections', label: 'Connections', colour: '#4ade80', icon: '⏻', on: true },
                { id: 'messages', label: 'Messages', colour: '#c4b5fd', icon: '✉', on: true },
                { id: 'system', label: 'System', colour: '#64748b', icon: '·', on: false },
            ];
            const GROUP = Object.fromEntries(GROUPS.map(g => [g.id, g]));

            function classify(row) {
                const c = row.category || '';
                const action = String(row.context?.action || '');
                if (c === 'Error' || /rejected|refused|failed|not_found|error/i.test(action)) return 'errors';
                if (c === 'TagLoss' || c === 'Tag' || c === 'FDR' || c === 'ServerFDR' || c === 'FdrResume') return 'tags';
                if (c === 'Couple') return 'coupling';
                if (['ServerSector', 'Ownership', 'Request', 'Requests', 'Primary', 'Mmi'].includes(c)) return 'sectors';
                if (['Resume', 'OzServer', 'Outage'].includes(c)) return 'connections';
                if (c === 'Message') return 'messages';
                return 'system';
            }

            // The facts worth seeing at a glance, from a line's context.
            function facts(row) {
                const x = row.context || {};
                const out = [];
                const list = v => Array.isArray(v) && v.length ? v.join(', ') : null;
                if (x.fdr_callsign) out.push(['flight', x.fdr_callsign]);
                if (x.sector) out.push(['sector', x.sector]);
                for (const k of ['claimed', 'released', 'requested', 'transferred', 'resumed', 'sectors']) { const v = list(x[k]); if (v) out.push([k, v]); }
                if (x.from?.callsign && x.to?.callsign) out.push(['from → to', `${x.from.callsign} → ${x.to.callsign}`]);
                else if (x.to_callsign) out.push(['to', x.to_callsign]);
                else if (x.from_callsign) out.push(['from', x.from_callsign]);
                const flights = list(x.fdr_authority_transferred) || list(x.restored_flights) || list(x.fdr_authority_cleared);
                if (flights) out.push(['tags', flights]);
                if (x.state) out.push(['state', x.state]);
                return out.slice(0, 5);
            }

            // ---------------------------------------------------------------------------------
            // State
            // ---------------------------------------------------------------------------------
            const state = {
                server: 'all',
                events: [],            // every line loaded, oldest first
                seen: new Set(),
                groups: Object.fromEntries(GROUPS.map(g => [g.id, g.on])),
                raw: false,
                paused: false,
                focus: null,           // callsign the feed and details are narrowed to
                search: '',
                clients: [],           // connected plugin clients (admin), when messaging is enabled
                ops: {},               // server -> /map/ops
                versions: new Map(),   // callsign -> plugin version, from the logs
                lastPlugin: new Map(), // callsign -> { at, server, cid } from the logs
            };

            const keyOf = row => [row.controller_cid, row.controller_callsign, row.server, row.logged_at, row.sequence, row.category, row.message].join('|');
            const isApi = row => row.plugin_version === 'server';

            function ingest(rows) {
                const fresh = [];
                for (const row of rows) {
                    const key = keyOf(row);
                    if (state.seen.has(key)) continue;
                    state.seen.add(key);
                    row._group = classify(row);
                    state.events.push(row);
                    fresh.push(row);
                    if (!isApi(row)) {
                        const cs = row.controller_callsign;
                        if (row.plugin_version) state.versions.set(cs, row.plugin_version);
                        const prev = state.lastPlugin.get(cs);
                        if (!prev || prev.at < row.logged_at) state.lastPlugin.set(cs, { at: row.logged_at, server: row.server, cid: row.controller_cid });
                    }
                }
                if (state.events.length > MAX_EVENTS) {
                    const drop = state.events.splice(0, state.events.length - MAX_EVENTS);
                    for (const row of drop) state.seen.delete(keyOf(row));
                }
                return fresh;
            }

            const visible = row =>
                state.groups[row._group]
                && (state.server === 'all' || row.server === state.server)
                && (!state.focus || row.controller_callsign === state.focus)
                && (!state.search || `${row.controller_callsign} ${row.category} ${row.message} ${JSON.stringify(row.context || {})}`.toLowerCase().includes(state.search));

            // ---------------------------------------------------------------------------------
            // Feed
            // ---------------------------------------------------------------------------------
            const feed = document.getElementById('feed');
            const nearBottom = () => feed.scrollHeight - feed.scrollTop - feed.clientHeight < 60;

            function eventNode(row) {
                const g = GROUP[row._group];
                const node = document.createElement('div');
                if (state.raw) {
                    node.className = 'oz-raw';
                    node.innerHTML = `${esc(row.logged_at)} <b>${esc(row.server)}</b> <b>${esc(row.controller_callsign)}</b> ${esc(row.plugin_version || '')} <span class="${row._group === 'errors' ? 'e' : ''}">[${esc(row.category)}] ${esc(row.message)}</span>${row.context ? ' ' + esc(JSON.stringify(row.context)) : ''}`;
                    return node;
                }
                node.className = `oz-ev g-${row._group}`;
                node.style.setProperty('--c', g.colour);
                const f = facts(row).map(([k, v]) => `<span class="oz-chip" title="${esc(k)}">${esc(v)}</span>`).join('');
                node.innerHTML = `
                    <time>${hhmmss(row.logged_at)}</time>
                    <span class="icon" title="${esc(g.label)}">${g.icon}</span>
                    <div>
                        <span class="who ${isApi(row) ? 'api' : ''}">${esc(row.controller_callsign)}</span>
                        <span class="oz-badge srv-${esc(row.server)}">${esc(row.server)}</span>
                        ${isApi(row) ? '<span class="oz-badge">API</span>' : ''}
                        <span class="msg">${esc(row.message)}</span>
                        ${f ? `<div class="facts">${f}</div>` : ''}
                    </div>`;
                node.addEventListener('click', e => {
                    if (e.target.closest('.who')) return selectController(row.controller_callsign);
                    const open = node.querySelector('pre');
                    if (open) return open.remove();
                    const pre = document.createElement('pre');
                    pre.textContent = JSON.stringify({ category: row.category, plugin_version: row.plugin_version, session_id: row.session_id, sequence: row.sequence, logged_at: row.logged_at, received_at: row.created_at, cid: row.controller_cid, context: row.context }, null, 2);
                    node.appendChild(pre);
                });
                return node;
            }

            function renderFeed() {
                const rows = state.events.filter(visible);
                feed.innerHTML = '';
                if (rows.length === 0) {
                    feed.innerHTML = '<div class="oz-empty">Nothing matches - try turning more groups on, or a longer history.</div>';
                } else {
                    const frag = document.createDocumentFragment();
                    for (const row of rows.slice(-1500)) frag.appendChild(eventNode(row));
                    feed.appendChild(frag);
                }
                feed.scrollTop = feed.scrollHeight;
                renderGroupCounts();
            }

            function appendFeed(rows) {
                const shown = rows.filter(visible);
                if (shown.length === 0) return renderGroupCounts();
                const stick = nearBottom();
                feed.querySelector('.oz-empty')?.remove();
                for (const row of shown) feed.appendChild(eventNode(row));
                while (feed.children.length > 1500) feed.removeChild(feed.firstChild);
                if (stick) feed.scrollTop = feed.scrollHeight;
                renderGroupCounts();
            }

            const groupsEl = document.getElementById('groups');
            function renderGroups() {
                groupsEl.innerHTML = '';
                for (const g of GROUPS) {
                    const b = document.createElement('button');
                    b.className = 'oz-toggle' + (state.groups[g.id] ? ' on' : '');
                    b.style.setProperty('--c', g.colour);
                    b.dataset.group = g.id;
                    b.innerHTML = `<i></i>${esc(g.label)} <span class="n"></span>`;
                    b.addEventListener('click', () => { state.groups[g.id] = !state.groups[g.id]; renderGroups(); renderFeed(); });
                    groupsEl.appendChild(b);
                }
                renderGroupCounts();
            }
            function renderGroupCounts() {
                const counts = {};
                for (const row of state.events) {
                    if (state.server !== 'all' && row.server !== state.server) continue;
                    if (state.focus && row.controller_callsign !== state.focus) continue;
                    counts[row._group] = (counts[row._group] || 0) + 1;
                }
                for (const b of groupsEl.querySelectorAll('.oz-toggle')) b.querySelector('.n').textContent = counts[b.dataset.group] || 0;
            }

            // ---------------------------------------------------------------------------------
            // Log polling: a backfill for the chosen history, then a short trailing window.
            // ---------------------------------------------------------------------------------
            const liveEl = document.getElementById('live');
            function setLive(on, text) { liveEl.classList.toggle('on', on); liveEl.querySelector('span').textContent = text; }

            // Through this site (/console/api/logs), which reads the API for the unlocked console.
            async function fetchLogs(since, limit) {
                const params = new URLSearchParams({ since, limit: String(limit) });
                if (state.server !== 'all') params.set('server', state.server);
                const r = await consoleFetch(`/console/api/logs?${params}`);
                if (r.status === 401) { window.location.reload(); throw new Error('locked'); }
                if (!r.ok || !Array.isArray(r.body)) throw new Error(`HTTP ${r.status}`);
                return r.body;
            }

            async function backfill() {
                state.events = []; state.seen.clear();
                feed.innerHTML = '<div class="oz-empty">Loading history…</div>';
                try {
                    ingest(await fetchLogs(document.getElementById('window').value, 5000));
                    setLive(true, 'Live');
                } catch { setLive(false, 'Feed unavailable'); }
                renderFeed(); renderStats(); renderControllers(); renderDetails();
            }

            async function tail() {
                if (state.paused) return;
                try {
                    const fresh = ingest(await fetchLogs('2 minutes', 1000));
                    setLive(true, 'Live');
                    if (fresh.length) { appendFeed(fresh); renderStats(); renderControllers(); if (!detailsBusy()) renderDetails(); }
                } catch { setLive(false, 'Feed unavailable'); }
            }

            // ---------------------------------------------------------------------------------
            // Connected controllers and sector ownership
            // ---------------------------------------------------------------------------------
            async function refreshState() {
                const servers = state.server === 'all' ? SERVERS : [state.server];
                await Promise.all(servers.map(async server => {
                    try {
                        const r = await fetch(`${apiBaseUrl}/api/v1/map/ops?server=${server}`, { headers: { Accept: 'application/json' } });
                        if (r.ok) state.ops[server] = await r.json();
                    } catch { /* keep the last */ }
                }));
                if (MESSAGING_ENABLED) {
                    const r = await consoleFetch(`/console/api/clients?server=${state.server}`);
                    if (r.ok && Array.isArray(r.body)) state.clients = r.body;
                    else if (r.status === 401) return window.location.reload();
                }
                renderStats(); renderControllers(); renderPicker(); if (!detailsBusy()) renderDetails();
            }

            const opsServers = () => (state.server === 'all' ? SERVERS : [state.server]).map(s => state.ops[s]).filter(Boolean);
            const sectorsOf = (callsign, server) => {
                const ops = state.ops[server];
                return ops ? ops.ownerships.filter(o => o.callsign === callsign).map(o => o.sector) : [];
            };
            const errorsOf = callsign => state.events.filter(r => r._group === 'errors' && r.controller_callsign === callsign);

            // Everyone worth listing: plugin clients calling now, plus anyone holding sectors or
            // seen in the logs within the last 5 minutes.
            function controllerRows() {
                const rows = new Map();
                const add = (callsign, server, extra) => {
                    const key = `${server}|${callsign}`;
                    rows.set(key, { callsign, server, ...(rows.get(key) || {}), ...extra });
                };
                for (const c of state.clients) add(c.callsign, c.server, { cid: c.cid, lastCall: c.last_seen_at, connected: true });
                for (const ops of opsServers()) for (const c of ops.controllers) add(c.callsign, ops.server, { cid: c.cid, holding: true, lastCall: c.last_seen_at });
                const recent = Date.now() - 5 * 60000;
                for (const [callsign, info] of state.lastPlugin) {
                    if (new Date(info.at).getTime() < recent) continue;
                    if (state.server !== 'all' && info.server !== state.server) continue;
                    add(callsign, info.server, { cid: info.cid, lastLog: info.at });
                }
                return [...rows.values()].sort((a, b) => a.callsign.localeCompare(b.callsign));
            }

            const ctlEl = document.getElementById('controllers');
            function renderControllers() {
                const q = document.getElementById('ctl-search').value.trim().toUpperCase();
                const rows = controllerRows().filter(r => !q || r.callsign.toUpperCase().includes(q));
                if (rows.length === 0) { ctlEl.innerHTML = '<div class="oz-empty">Nobody connected.</div>'; return; }
                ctlEl.innerHTML = '';
                for (const r of rows) {
                    const last = r.lastCall || r.lastLog;
                    const quietMs = last ? Date.now() - new Date(last).getTime() : Infinity;
                    const dot = r.connected || r.holding ? (quietMs > 30000 ? 'stale' : '') : 'off';
                    const held = sectorsOf(r.callsign, r.server);
                    const errs = errorsOf(r.callsign).length;
                    const b = document.createElement('button');
                    b.className = 'oz-ctl' + (state.focus === r.callsign ? ' selected' : '');
                    b.innerHTML = `
                        <i class="oz-dot ${dot}"></i>
                        <div class="main">
                            <div class="title">${esc(r.callsign)}</div>
                            <div class="sub"><span class="oz-badge srv-${esc(r.server)}">${esc(r.server)}</span> ${esc(state.versions.get(r.callsign) || 'version ?')} · ${held.length ? esc(held.length + ' sector' + (held.length === 1 ? '' : 's')) : 'no sectors'}</div>
                        </div>
                        <div class="right">${errs ? `<div class="errs">${errs} err</div>` : ''}<div>${esc(ago(last))}</div></div>`;
                    b.addEventListener('click', () => selectController(state.focus === r.callsign ? null : r.callsign));
                    ctlEl.appendChild(b);
                }
            }
            document.getElementById('ctl-search').addEventListener('input', renderControllers);

            // ---------------------------------------------------------------------------------
            // Top bar
            // ---------------------------------------------------------------------------------
            function renderStats() {
                const ops = opsServers();
                const holding = ops.reduce((n, o) => n + o.controllers.length, 0);
                const pending = ops.reduce((n, o) => n + o.requests.filter(r => !r.rejected_at).length, 0);
                const inScope = state.events.filter(r => state.server === 'all' || r.server === state.server);
                const errs = inScope.filter(r => r._group === 'errors').length;
                const losses = inScope.filter(r => r.category === 'TagLoss').length;
                const connected = MESSAGING_ENABLED ? state.clients.length : controllerRows().length;
                const stat = (n, label, cls = '') => `<div class="oz-stat ${cls}"><b>${n}</b><small>${label}</small></div>`;
                document.getElementById('stats').innerHTML =
                    stat(connected, 'plugins connected') +
                    stat(holding, 'holding sectors') +
                    stat(pending, 'pending requests', pending ? 'warn' : '') +
                    stat(errs, `errors (${document.getElementById('window').selectedOptions[0].text})`, errs ? 'bad' : '') +
                    stat(losses, 'tag losses', losses ? 'warn' : '') +
                    stat(inScope.length, 'log lines');
            }

            // ---------------------------------------------------------------------------------
            // Details: the network, or one controller
            // ---------------------------------------------------------------------------------
            const detailsEl = document.getElementById('details');
            // Not redrawn under the pointer while someone is reading or selecting in it.
            const detailsBusy = () => detailsEl.matches(':hover') && window.getSelection()?.toString();

            function renderDetails() {
                if (state.focus) return renderControllerDetails(state.focus);
                const ops = opsServers();
                const inScope = state.events.filter(r => state.server === 'all' || r.server === state.server);
                const errors = inScope.filter(r => r._group === 'errors');
                const top = new Map();
                for (const r of errors) top.set(r.message, (top.get(r.message) || 0) + 1);
                const topRows = [...top.entries()].sort((a, b) => b[1] - a[1]).slice(0, 8);
                const requests = ops.flatMap(o => o.requests.filter(r => !r.rejected_at).map(r => ({ ...r, server: o.server })));
                detailsEl.innerHTML = `
                    <div class="oz-kind">Network</div>
                    <h3>${state.server === 'all' ? 'All servers' : esc(state.server)}</h3>
                    <div class="oz-summary">
                        <div><b>${MESSAGING_ENABLED ? state.clients.length : controllerRows().length}</b><small>Plugins connected</small></div>
                        <div><b>${ops.reduce((n, o) => n + o.ownerships.length, 0)}</b><small>Sectors held</small></div>
                        <div><b>${requests.length}</b><small>Pending requests</small></div>
                        <div><b>${errors.length}</b><small>Errors loaded</small></div>
                    </div>
                    <div class="oz-section-title">Pending sector requests</div>
                    ${requests.length ? requests.map(r => `<div class="oz-mini"><time>${esc(ago(r.created_at))}</time><b>${esc(r.requesting_callsign)}</b> wants <span class="oz-chip">${esc(r.sector)}</span> from <b>${esc(r.target_callsign)}</b></div>`).join('') : '<div class="oz-empty">None.</div>'}
                    <div class="oz-section-title">Most common errors</div>
                    ${topRows.length ? topRows.map(([m, n]) => `<div class="oz-toprow"><b>${n}×</b><span>${esc(m)}</span></div>`).join('') : '<div class="oz-empty">No errors in the loaded history.</div>'}
                    <div class="oz-hint" style="margin-top:12px">Click a controller on the left, or a callsign in the feed, to focus on them.</div>`;
            }

            function renderControllerDetails(callsign) {
                const row = controllerRows().find(r => r.callsign === callsign);
                const server = row?.server || state.lastPlugin.get(callsign)?.server || 'live';
                const held = sectorsOf(callsign, server);
                const ops = state.ops[server];
                const incoming = ops ? ops.requests.filter(r => !r.rejected_at && r.target_callsign === callsign) : [];
                const outgoing = ops ? ops.requests.filter(r => !r.rejected_at && r.requesting_callsign === callsign) : [];
                const errors = errorsOf(callsign).slice(-10).reverse();
                const losses = state.events.filter(r => r.controller_callsign === callsign && r.category === 'TagLoss').slice(-6).reverse();
                detailsEl.innerHTML = `
                    <div class="oz-kind">Controller</div>
                    <h3>${esc(callsign)}</h3>
                    <dl class="oz-kv">
                        <dt>Server</dt><dd><span class="oz-badge srv-${esc(server)}">${esc(server)}</span></dd>
                        <dt>CID</dt><dd>${esc(row?.cid ?? state.lastPlugin.get(callsign)?.cid ?? '?')}</dd>
                        <dt>Plugin</dt><dd>${esc(state.versions.get(callsign) || 'unknown')}</dd>
                        <dt>Last call</dt><dd>${row?.lastCall ? esc(ago(row.lastCall)) + ' ago' : 'not calling'}</dd>
                        <dt>Last log</dt><dd>${state.lastPlugin.get(callsign) ? esc(ago(state.lastPlugin.get(callsign).at)) + ' ago' : '-'}</dd>
                    </dl>
                    <div class="oz-section-title">Sectors held (${held.length})</div>
                    ${held.length ? held.map(s => `<span class="oz-chip">${esc(s)}</span>`).join('') : '<div class="oz-empty">None.</div>'}
                    <div class="oz-section-title">Requests</div>
                    ${incoming.concat(outgoing).length ? incoming.map(r => `<div class="oz-mini">← <b>${esc(r.requesting_callsign)}</b> wants <span class="oz-chip">${esc(r.sector)}</span></div>`).join('') + outgoing.map(r => `<div class="oz-mini">→ asked <b>${esc(r.target_callsign)}</b> for <span class="oz-chip">${esc(r.sector)}</span></div>`).join('') : '<div class="oz-empty">None pending.</div>'}
                    <div class="oz-section-title">Recent errors</div>
                    ${errors.length ? errors.map(r => `<div class="oz-mini"><time>${hhmmss(r.logged_at)}</time>${esc(r.message)}</div>`).join('') : '<div class="oz-empty">None in the loaded history.</div>'}
                    <div class="oz-section-title">Tag losses</div>
                    ${losses.length ? losses.map(r => `<div class="oz-mini"><time>${hhmmss(r.logged_at)}</time>${esc(r.message)}</div>`).join('') : '<div class="oz-empty">None.</div>'}
                    <div class="oz-actions">
                        <button class="oz-btn" id="d-clear">Show everyone</button>
                        ${MESSAGING_ENABLED ? '<button class="oz-btn primary" id="d-message">Send message</button>' : ''}
                    </div>`;
                document.getElementById('d-clear').addEventListener('click', () => selectController(null));
                document.getElementById('d-message')?.addEventListener('click', () => messageTo(callsign, server));
            }

            function selectController(callsign) {
                state.focus = callsign;
                const focus = document.getElementById('focus');
                focus.hidden = !callsign;
                document.getElementById('focus-name').textContent = callsign || '';
                showTab('details');
                renderFeed(); renderControllers(); renderDetails();
            }
            document.getElementById('focus-clear').addEventListener('click', () => selectController(null));

            // ---------------------------------------------------------------------------------
            // Right-hand tabs
            // ---------------------------------------------------------------------------------
            let onMessagesShown = null;
            function showTab(name) {
                for (const b of document.querySelectorAll('.oz-tabs button')) b.classList.toggle('active', b.dataset.tab === name);
                for (const body of document.querySelectorAll('.oz-right [data-body]')) body.hidden = body.dataset.body !== name;
                if (name === 'messages' && onMessagesShown) onMessagesShown();
            }
            for (const b of document.querySelectorAll('.oz-tabs button')) b.addEventListener('click', () => showTab(b.dataset.tab));

            // ---------------------------------------------------------------------------------
            // Messages: compose (To = everyone, or a checklist of who is connected) and the sent list.
            // ---------------------------------------------------------------------------------
            const chosen = new Set(); // "server|callsign"
            const pickerList = document.getElementById('picker-list');
            const msgServer = document.getElementById('m-server');

            function pickable() {
                const server = msgServer.value;
                return state.clients.filter(c => server === 'all' || c.server === server)
                    .sort((a, b) => a.callsign.localeCompare(b.callsign));
            }

            const serverOf = k => k.slice(0, k.indexOf('|'));
            const callsignOf = k => k.slice(k.indexOf('|') + 1);

            // Chosen recipients who cannot be sent to as things stand: their plugin has stopped
            // calling, or they are not on the server picked above. They stay ticked - taking one
            // out would quietly leave "Everyone" - and nothing is sent until they are unticked
            // or back.
            function unreachable() {
                const valid = new Set(pickable().map(c => `${c.server}|${c.callsign}`));
                return [...chosen].filter(k => !valid.has(k));
            }

            function renderPicker() {
                const options = pickable();
                const row = (k, note) => `<label><input type="checkbox" data-key="${esc(k)}" ${chosen.has(k) ? 'checked' : ''}> ${esc(callsignOf(k))} <span class="oz-badge srv-${esc(serverOf(k))}">${esc(serverOf(k))}</span>${note}</label>`;
                const away = k => `<span class="oz-badge warn">${msgServer.value !== 'all' && serverOf(k) !== msgServer.value ? 'not on this server' : 'not connected'}</span>`;

                pickerList.innerHTML = `<label class="all"><input type="checkbox" data-all ${chosen.size === 0 ? 'checked' : ''}> Everyone</label>` +
                    unreachable().map(k => row(k, ' ' + away(k))).join('') +
                    (options.length ? options.map(c => row(`${c.server}|${c.callsign}`, '')).join('')
                        : '<div class="oz-empty">No controllers connected.</div>');

                pickerList.querySelector('[data-all]').addEventListener('change', () => { chosen.clear(); renderPicker(); });
                for (const box of pickerList.querySelectorAll('[data-key]')) {
                    box.addEventListener('change', () => { if (box.checked) chosen.add(box.dataset.key); else chosen.delete(box.dataset.key); renderPicker(); });
                }

                const label = document.getElementById('picker-label');
                const names = [...chosen].map(callsignOf);
                label.textContent = names.length === 0 ? 'Everyone' : names.length <= 2 ? names.join(', ') : `${names.length} controllers`;
                // Chosen controllers get it whenever their plugin next asks - no "show for".
                document.getElementById('expires-row').hidden = names.length > 0;
                document.getElementById('targeted-note').hidden = names.length === 0;
            }

            document.getElementById('picker-btn').addEventListener('click', () => { pickerList.hidden = !pickerList.hidden; });
            document.addEventListener('click', e => { if (!e.target.closest('#picker')) pickerList.hidden = true; });
            msgServer.addEventListener('change', renderPicker);

            function messageTo(callsign, server) {
                msgServer.value = server;
                chosen.clear();
                chosen.add(`${server}|${callsign}`);
                renderPicker();
                showTab('messages');
                document.getElementById('m-body').focus();
            }

            function startMessages() {
                const form = document.getElementById('compose');
                const status = document.getElementById('m-status');
                const sendButton = document.getElementById('m-send');
                const list = document.getElementById('sent');
                const expanded = new Set();
                const setStatus = (text, kind) => { status.textContent = text; status.className = 'oz-status' + (kind ? ' ' + kind : ''); };

                function render(messages) {
                    if (!Array.isArray(messages) || messages.length === 0) { list.innerHTML = '<div class="oz-empty">Nothing sent in the last 7 days.</div>'; return; }
                    const now = Date.now();
                    list.innerHTML = '';
                    for (const m of messages) {
                        const expired = new Date(m.expires_at).getTime() < now;
                        const read = m.receipts.filter(r => r.read_at).length;
                        const to = Array.isArray(m.callsigns) && m.callsigns.length ? m.callsigns.map(esc).join(', ') : 'everyone';
                        const card = document.createElement('div');
                        card.className = 'oz-msg' + (m.withdrawn_at || expired ? ' dim' : '');
                        card.innerHTML = `
                            <div class="head">
                                <b>${m.title ? esc(m.title) : '<span style="color:var(--muted)">(no title)</span>'}</b>
                                ${m.allow_reply ? '<span class="oz-badge accent">replies</span>' : ''}
                                ${m.withdrawn_at ? '<span class="oz-badge bad">withdrawn</span>' : expired ? '<span class="oz-badge">expired</span>' : ''}
                                ${!m.withdrawn_at && !expired ? '<button class="oz-btn danger" data-withdraw>Withdraw</button>' : ''}
                            </div>
                            <div class="meta">To ${to} · ${m.server ? esc(m.server) : 'all servers'} · ${stamp(m.created_at)}</div>
                            <div class="body">${esc(m.body)}</div>
                            <div class="stats">Delivered ${m.receipts.length} · read ${read} · ${m.replies.length} repl${m.replies.length === 1 ? 'y' : 'ies'} ${m.receipts.length ? (expanded.has(m.id) ? '▾' : '▸') : ''}</div>
                            ${expanded.has(m.id) ? `<div class="receipts">${m.receipts.map(r => `${esc(r.callsign)} (${esc(r.server)}) - ${r.read_at ? 'read ' + stamp(r.read_at) : 'not read yet'}`).join('<br>')}</div>` : ''}
                            ${m.replies.map(r => `<div class="reply"><div class="who">${esc(r.callsign)} (${esc(r.server)}) · ${stamp(r.created_at)}</div><div class="text">${esc(r.body)}</div></div>`).join('')}`;
                        card.querySelector('.stats').addEventListener('click', () => { expanded.has(m.id) ? expanded.delete(m.id) : expanded.add(m.id); render(messages); });
                        card.querySelector('[data-withdraw]')?.addEventListener('click', async () => {
                            if (!confirm('Withdraw this message? Controllers who have not had it yet will not get it.')) return;
                            const r = await consoleFetch(`/console/api/messages/${m.id}/withdraw`, { method: 'POST' });
                            if (!r.ok) alert(r.body?.message || `Could not withdraw (HTTP ${r.status}).`);
                            load();
                        });
                        list.appendChild(card);
                    }
                }

                async function load() {
                    const r = await consoleFetch('/console/api/messages');
                    if (r.status === 401) return window.location.reload();
                    if (!r.ok) { list.innerHTML = `<div class="oz-empty">${esc(r.body?.message || 'Could not load messages.')}</div>`; return; }
                    render(r.body);
                }

                form.addEventListener('submit', async e => {
                    e.preventDefault();
                    const server = msgServer.value;
                    const payload = {
                        title: document.getElementById('m-title').value.trim(),
                        body: document.getElementById('m-body').value.trim(),
                        allow_reply: document.getElementById('m-reply').checked,
                        expires_minutes: Number(document.getElementById('m-expires').value),
                    };
                    if (!payload.body) return setStatus('Write a message first.', 'err');
                    const missing = unreachable();
                    if (missing.length) return setStatus(`Not sent: ${missing.map(callsignOf).join(', ')} cannot be reached here - untick them, or wait until they are back.`, 'err');

                    // Everyone: one message. Chosen controllers: one per server they are on, since
                    // the API matches a callsign on whichever servers the message covers and the
                    // same callsign on a SweatBox is somebody else.
                    const sends = chosen.size === 0 ? [{ server, keys: [] }]
                        : [...new Set([...chosen].map(serverOf))].map(s => ({ server: s, keys: [...chosen].filter(k => serverOf(k) === s) }));
                    const audience = chosen.size ? [...chosen].map(callsignOf).join(', ') : `everyone on ${server === 'all' ? 'all servers' : server}`;
                    if (!confirm(`Send to ${audience}?`)) return;

                    sendButton.disabled = true;
                    setStatus('Sending…');
                    const failed = [];
                    for (const send of sends) {
                        const r = await consoleFetch('/console/api/messages', { method: 'POST', body: JSON.stringify({ ...payload, server: send.server, callsigns: send.keys.map(callsignOf) }) });
                        if (r.ok) continue;
                        const errors = r.body?.errors ? Object.values(r.body.errors).flat().join(' ') : '';
                        failed.push({ send, reason: errors || r.body?.message || `Could not send (HTTP ${r.status}).` });
                    }
                    sendButton.disabled = false;
                    if (failed.length === 0) {
                        document.getElementById('m-title').value = '';
                        document.getElementById('m-body').value = '';
                        setStatus(`Sent to ${audience}.`, 'ok');
                    } else if (sends.length === 1) {
                        setStatus(failed[0].reason, 'err');
                    } else {
                        // Some servers took it: those controllers are unticked, so sending again
                        // reaches only the ones still to go. The failed ones stay ticked.
                        for (const send of sends) if (!failed.some(f => f.send === send)) for (const k of send.keys) chosen.delete(k);
                        renderPicker();
                        setStatus(`Not sent to ${failed.flatMap(f => f.send.keys.map(callsignOf)).join(', ')}: ${failed[0].reason}`, 'err');
                    }
                    if (failed.length < sends.length) load();
                });

                let timer = null;
                onMessagesShown = () => { load(); if (!timer) timer = setInterval(() => { if (!document.querySelector('[data-body="messages"]').hidden) load(); }, STATE_POLL_MS); };
                renderPicker();
            }

            // ---------------------------------------------------------------------------------
            // Map, controls, start-up
            // ---------------------------------------------------------------------------------
            function setMap() {
                const server = state.server === 'all' ? 'live' : state.server;
                document.getElementById('map').src = `/live-map?embed=1&server=${encodeURIComponent(server)}`;
                document.getElementById('map-open').href = `/live-map?server=${encodeURIComponent(server)}`;
            }

            function start() {
                renderGroups();
                renderDetails();
                setMap();

                document.getElementById('server').addEventListener('change', e => {
                    state.server = e.target.value;
                    state.ops = {};
                    setMap(); backfill(); refreshState();
                });
                document.getElementById('window').addEventListener('change', backfill);
                document.getElementById('search').addEventListener('input', e => { state.search = e.target.value.trim().toLowerCase(); renderFeed(); });
                document.getElementById('view').addEventListener('click', e => {
                    state.raw = !state.raw;
                    e.target.textContent = state.raw ? 'Readable' : 'Raw';
                    e.target.classList.toggle('on', state.raw);
                    renderFeed();
                });
                document.getElementById('pause').addEventListener('click', e => {
                    state.paused = !state.paused;
                    e.target.textContent = state.paused ? 'Resume' : 'Pause';
                    e.target.classList.toggle('on', state.paused);
                    setLive(!state.paused, state.paused ? 'Paused' : 'Live');
                });
                document.getElementById('clear').addEventListener('click', () => { state.events = []; state.seen.clear(); renderFeed(); renderStats(); });
                document.getElementById('map-toggle').addEventListener('click', e => {
                    const center = document.getElementById('center');
                    center.classList.toggle('map-collapsed');
                    e.target.textContent = center.classList.contains('map-collapsed') ? 'Show' : 'Hide';
                });

                if (MESSAGING_ENABLED) startMessages();
                backfill();
                refreshState();
                setInterval(tail, LOG_POLL_MS);
                setInterval(refreshState, STATE_POLL_MS);
            }

            if (INITIALLY_UNLOCKED) unlock();
        </script>
    </body>
</html>
