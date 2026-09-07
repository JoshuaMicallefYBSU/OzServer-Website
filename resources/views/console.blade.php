<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OzServer | Console</title>
        <style>
            html, body { margin: 0; height: 100%; background: #020617; color: #e2e8f0; font-family: ui-sans-serif, system-ui, sans-serif; }

            .oz-gate {
                position: fixed; inset: 0; align-items: center; justify-content: center;
                background: #020617;
            }
            .oz-gate:not([hidden]) { display: flex; }
            .oz-gate-box {
                background: rgba(15, 23, 42, 0.9); border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 12px; padding: 32px; width: 280px; text-align: center;
            }
            .oz-gate-box h1 { font-size: 16px; margin: 0 0 16px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
            .oz-gate-box input {
                width: 100%; box-sizing: border-box; background: #0f172a; color: #e2e8f0;
                border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 6px; padding: 8px 10px;
                font: inherit; margin-bottom: 12px;
            }
            .oz-gate-box button {
                width: 100%; background: #22d3ee; color: #0f172a; border: none; border-radius: 6px;
                padding: 8px 10px; font: inherit; font-weight: 700; cursor: pointer;
            }
            .oz-gate-box button:hover { background: #67e8f9; }
            .oz-gate-error { color: #f87171; font-size: 12px; margin: 10px 0 0; }

            #app { position: absolute; inset: 0; flex-direction: column; }
            #app:not([hidden]) { display: flex; }

            .oz-toolbar {
                display: flex; align-items: center; gap: 12px; padding: 10px 16px;
                background: rgba(15, 23, 42, 0.9); border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                flex-wrap: wrap;
            }
            .oz-toolbar h1 { font-size: 13px; margin: 0; color: #67e8f9; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
            .oz-toolbar label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-right: 4px; }
            .oz-toolbar select, .oz-toolbar input[type="text"] {
                background: #0f172a; color: #e2e8f0; border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 6px; padding: 4px 8px; font: inherit; font-size: 12px;
            }
            .oz-toolbar input[type="text"] { width: 120px; }
            .oz-toolbar button {
                background: transparent; color: #94a3b8; border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 6px; padding: 4px 10px; font: inherit; font-size: 12px; cursor: pointer;
            }
            .oz-toolbar button:hover { color: #e2e8f0; border-color: rgba(255, 255, 255, 0.3); }
            .oz-toolbar button.active { color: #0f172a; background: #22d3ee; border-color: #22d3ee; }
            .oz-toolbar .oz-spacer { flex: 1; }
            .oz-toolbar .oz-count { font-size: 11px; color: #64748b; white-space: nowrap; }
            .oz-toolbar .oz-dot {
                width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px;
                background: #f87171;
            }
            .oz-toolbar .oz-dot.live { background: #4ade80; }

            #log {
                flex: 1; overflow-y: auto; padding: 8px 16px; font-family: ui-monospace, monospace;
                font-size: 12px; line-height: 1.6;
            }
            .oz-line { white-space: pre-wrap; word-break: break-word; padding: 2px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.03); }
            .oz-line .t { color: #64748b; }
            .oz-line .srv { display: inline-block; min-width: 34px; text-align: center; border-radius: 4px; padding: 0 4px; font-size: 10px; font-weight: 700; margin: 0 6px; }
            .oz-line .srv-live { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
            .oz-line .srv-sb1 { background: rgba(250, 204, 21, 0.15); color: #facc15; }
            .oz-line .srv-sb2 { background: rgba(167, 139, 250, 0.15); color: #a78bfa; }
            .oz-line .who { color: #67e8f9; }
            .oz-line .cat { color: #94a3b8; }
            .oz-line .cat-ServerSector, .oz-line .cat-ServerFDR { color: #22d3ee; }
            .oz-line .cat-Tag { color: #facc15; }
            .oz-line .cat-Ownership { color: #4ade80; }
            .oz-line .msg { color: #e2e8f0; }
            .oz-line .ctx {
                display: block; color: #64748b; font-size: 11px; margin: 2px 0 0 0; cursor: pointer;
            }
            .oz-line .ctx pre { margin: 4px 0 0; white-space: pre-wrap; word-break: break-word; }
            .oz-empty { color: #64748b; padding: 40px 0; text-align: center; }
        </style>
    </head>
    <body>
        <div id="gate" class="oz-gate">
            <div class="oz-gate-box">
                <h1>OzServer Console</h1>
                <input type="password" id="gate-password" placeholder="Password" autocomplete="off">
                <button id="gate-submit">Enter</button>
                <p id="gate-error" class="oz-gate-error" hidden>Incorrect password.</p>
            </div>
        </div>

        <div id="app" hidden>
            <div class="oz-toolbar">
                <h1><span id="live-dot" class="oz-dot"></span>Console</h1>

                <label for="f-server">Server</label>
                <select id="f-server">
                    <option value="">All</option>
                    <option value="live">Live VATSIM</option>
                    <option value="sb1">SweatBox 1</option>
                    <option value="sb2">SweatBox 2</option>
                </select>

                <label for="f-category">Category</label>
                <input type="text" id="f-category" placeholder="e.g. Tag">

                <label for="f-callsign">Callsign</label>
                <input type="text" id="f-callsign" placeholder="e.g. BN-ARL_CTR">

                <button id="f-pause">Pause</button>
                <button id="f-clear">Clear</button>

                <div class="oz-spacer"></div>
                <span id="count" class="oz-count"></span>
            </div>
            <div id="log"></div>
        </div>

        <script>
            const apiBaseUrl = @json(rtrim(config('services.ozserver_api.url'), '/'));
            const CONSOLE_PASSWORD = @json((string) config('services.ozserver_api.console_password'));
            const POLL_INTERVAL_MS = 2000;
            const POLL_WINDOW = '2 minutes';
            const MAX_LINES = 2000;

            // --- Password gate. Client-side only, deliberately - see config/services.php's own
            // comment on what this is (and is not) protecting. Persisted per browser tab session
            // only, not indefinitely, so it needs re-entering after the tab is actually closed.
            const gate = document.getElementById('gate');
            const app = document.getElementById('app');

            function unlock() {
                gate.hidden = true;
                app.hidden = false;
                startConsole();
            }

            function tryUnlock() {
                const value = document.getElementById('gate-password').value;
                if (CONSOLE_PASSWORD.length > 0 && value === CONSOLE_PASSWORD) {
                    sessionStorage.setItem('oz-console-unlocked', '1');
                    unlock();
                } else {
                    document.getElementById('gate-error').hidden = false;
                }
            }

            document.getElementById('gate-submit').addEventListener('click', tryUnlock);
            document.getElementById('gate-password').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') tryUnlock();
            });

            if (sessionStorage.getItem('oz-console-unlocked') === '1') {
                unlock();
            }

            // --- The console itself. A polling tail over GET /api/v1/client-logs (the same
            // endpoint the ARL/MNN-style investigations already use by hand) - there is no push
            // channel for log lines (see events.ts: writes to /client-logs deliberately publish no
            // signal, since every controller posts them continuously), so this just re-asks a short
            // trailing window every couple of seconds and dedupes against what it has already
            // rendered, rather than needing a cursor the endpoint doesn't offer.
            function startConsole() {
                const logEl = document.getElementById('log');
                const countEl = document.getElementById('count');
                const liveDot = document.getElementById('live-dot');
                const serverFilter = document.getElementById('f-server');
                const categoryFilter = document.getElementById('f-category');
                const callsignFilter = document.getElementById('f-callsign');
                const pauseBtn = document.getElementById('f-pause');
                const clearBtn = document.getElementById('f-clear');

                const seen = new Set();
                let lineCount = 0;
                let paused = false;
                let timer = null;

                function rowKey(row) {
                    return [row.controller_cid, row.controller_callsign, row.server, row.logged_at, row.sequence, row.category, row.message].join('|');
                }

                function formatTime(iso) {
                    const d = new Date(iso);
                    return d.toISOString().slice(11, 23) + 'z';
                }

                function nearBottom() {
                    return logEl.scrollHeight - logEl.scrollTop - logEl.clientHeight < 40;
                }

                function renderRow(row) {
                    const wasNearBottom = nearBottom();
                    const line = document.createElement('div');
                    line.className = 'oz-line';

                    // Every one of these is self-asserted by whichever plugin session sent it
                    // (server, callsign and category no less than message - see auth.ts/
                    // client-logs.ts, none of it identity-verified), so all of it is escaped before
                    // going anywhere near innerHTML, not just the message text.
                    const server = escapeHtml(row.server);
                    const callsign = escapeHtml(row.controller_callsign);
                    const category = escapeHtml(row.category);
                    const hasContext = row.context && Object.keys(row.context).length > 0;
                    line.innerHTML = `
                        <span class="t">${formatTime(row.logged_at)}</span>
                        <span class="srv srv-${server}">${server}</span>
                        <span class="who">${callsign}</span>
                        <span class="cat cat-${category}">[${category}]</span>
                        <span class="msg">${escapeHtml(row.message)}</span>
                        ${hasContext ? `<span class="ctx" data-open="0">context ▸</span>` : ''}
                    `;

                    if (hasContext) {
                        const ctx = line.querySelector('.ctx');
                        ctx.addEventListener('click', () => {
                            if (ctx.dataset.open === '1') {
                                ctx.textContent = 'context ▸';
                                ctx.dataset.open = '0';
                                const pre = ctx.nextElementSibling;
                                if (pre) pre.remove();
                            } else {
                                ctx.textContent = 'context ▾';
                                ctx.dataset.open = '1';
                                const pre = document.createElement('pre');
                                pre.textContent = JSON.stringify(row.context, null, 2);
                                ctx.insertAdjacentElement('afterend', pre);
                            }
                        });
                    }

                    logEl.appendChild(line);
                    lineCount++;

                    while (logEl.children.length > MAX_LINES) {
                        logEl.removeChild(logEl.firstChild);
                    }

                    countEl.textContent = `${lineCount} line${lineCount === 1 ? '' : 's'}`;

                    if (wasNearBottom) {
                        logEl.scrollTop = logEl.scrollHeight;
                    }
                }

                function escapeHtml(value) {
                    const div = document.createElement('div');
                    div.textContent = value;
                    return div.innerHTML;
                }

                async function poll() {
                    if (paused) return;

                    const params = new URLSearchParams({
                        controller_cid: '999999',
                        controller_callsign: 'CONSOLE',
                        since: POLL_WINDOW,
                        limit: '500',
                    });
                    if (serverFilter.value) params.set('server', serverFilter.value);
                    if (categoryFilter.value.trim()) params.set('category', categoryFilter.value.trim());
                    if (callsignFilter.value.trim()) params.set('callsign', callsignFilter.value.trim());

                    try {
                        const response = await fetch(`${apiBaseUrl}/api/v1/client-logs?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        const rows = await response.json();

                        const wasEmpty = logEl.querySelector('.oz-empty');
                        if (wasEmpty) wasEmpty.remove();

                        for (const row of rows) {
                            const key = rowKey(row);
                            if (seen.has(key)) continue;
                            seen.add(key);
                            renderRow(row);
                        }

                        liveDot.classList.add('live');
                    } catch (err) {
                        liveDot.classList.remove('live');
                    }
                }

                function resetView() {
                    logEl.innerHTML = '<div class="oz-empty">Waiting for log lines…</div>';
                    seen.clear();
                    lineCount = 0;
                    countEl.textContent = '';
                    poll();
                }

                pauseBtn.addEventListener('click', () => {
                    paused = !paused;
                    pauseBtn.textContent = paused ? 'Resume' : 'Pause';
                    pauseBtn.classList.toggle('active', paused);
                    liveDot.classList.remove('live');
                });

                clearBtn.addEventListener('click', resetView);
                serverFilter.addEventListener('change', resetView);
                categoryFilter.addEventListener('change', resetView);
                callsignFilter.addEventListener('change', resetView);

                resetView();
                timer = setInterval(poll, POLL_INTERVAL_MS);
            }
        </script>
    </body>
</html>
