<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
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

            /* Logs / Messages switch */
            .oz-tabs { display: inline-flex; gap: 4px; }
            #log-controls { display: contents; }
            #log-controls[hidden] { display: none; }

            /* Developer messages */
            #messages { flex: 1; display: flex; min-height: 0; }
            #messages[hidden] { display: none; }
            .oz-compose {
                width: 360px; flex-shrink: 0; padding: 16px; border-right: 1px solid rgba(255, 255, 255, 0.1);
                overflow-y: auto; display: flex; flex-direction: column; gap: 10px; font-size: 12px;
            }
            .oz-compose h2, .oz-sent h2 { font-size: 12px; margin: 0 0 4px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
            .oz-compose label { color: #94a3b8; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
            .oz-compose input[type="text"], .oz-compose select, .oz-compose textarea {
                width: 100%; box-sizing: border-box; background: #0f172a; color: #e2e8f0;
                border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 6px; padding: 6px 8px; font: inherit; font-size: 12px;
            }
            .oz-compose textarea { min-height: 140px; resize: vertical; }
            .oz-compose .oz-row { display: flex; gap: 8px; }
            .oz-compose .oz-row > div { flex: 1; }
            .oz-compose .oz-check { display: flex; align-items: center; gap: 6px; color: #e2e8f0; text-transform: none; letter-spacing: 0; font-size: 12px; }
            .oz-compose button.oz-send {
                background: #22d3ee; color: #0f172a; border: none; border-radius: 6px; padding: 8px 10px;
                font: inherit; font-weight: 700; cursor: pointer;
            }
            .oz-compose button.oz-send:disabled { opacity: 0.5; cursor: default; }
            .oz-compose .oz-hint { color: #64748b; font-size: 11px; }
            .oz-compose .oz-status { font-size: 12px; min-height: 16px; }
            .oz-compose .oz-status.ok { color: #4ade80; }
            .oz-compose .oz-status.err { color: #f87171; }
            .oz-sent { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
            .oz-msg {
                background: rgba(15, 23, 42, 0.9); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px;
                padding: 12px 14px; font-size: 12px;
            }
            .oz-msg.withdrawn, .oz-msg.expired { opacity: 0.55; }
            .oz-msg .oz-msg-head { display: flex; align-items: baseline; gap: 8px; }
            .oz-msg .oz-msg-title { font-weight: 700; font-size: 13px; color: #e2e8f0; }
            .oz-msg .oz-msg-meta { color: #64748b; font-size: 11px; margin: 4px 0 8px; }
            .oz-msg .oz-msg-body { white-space: pre-wrap; word-break: break-word; color: #cbd5e1; }
            .oz-msg .oz-msg-stats { margin-top: 8px; color: #94a3b8; font-size: 11px; cursor: pointer; }
            .oz-msg .oz-msg-receipts { margin-top: 6px; color: #64748b; font-size: 11px; font-family: ui-monospace, monospace; }
            .oz-msg .oz-reply { margin-top: 8px; border-left: 2px solid #22d3ee; padding: 4px 0 4px 10px; }
            .oz-msg .oz-reply .who { color: #67e8f9; font-size: 11px; }
            .oz-msg .oz-reply .text { white-space: pre-wrap; word-break: break-word; color: #e2e8f0; }
            .oz-msg button.oz-withdraw {
                margin-left: auto; background: transparent; color: #f87171; border: 1px solid rgba(248, 113, 113, 0.4);
                border-radius: 6px; padding: 2px 8px; font: inherit; font-size: 11px; cursor: pointer;
            }
            .oz-badge { font-size: 10px; font-weight: 700; border-radius: 4px; padding: 0 5px; }
            .oz-badge.reply { background: rgba(34, 211, 238, 0.15); color: #22d3ee; }
            .oz-badge.withdrawn { background: rgba(248, 113, 113, 0.15); color: #f87171; }
            .oz-badge.expired { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
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

                <div class="oz-tabs">
                    <button id="tab-logs" class="active">Logs</button>
                    <button id="tab-messages" @if (! $messagingEnabled) hidden @endif>Messages</button>
                </div>

                <span id="log-controls">
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
                </span>

                <div class="oz-spacer"></div>
                <span id="count" class="oz-count"></span>
                <button id="lock">Lock</button>
            </div>
            <div id="log"></div>

            <div id="messages" hidden>
                <form class="oz-compose" id="compose" autocomplete="off">
                    <h2>Send a message</h2>
                    <div class="oz-hint">Shown in a popup in each controller's vatSys, with a sound. Anyone who connects before it expires sees it then.</div>

                    <div class="oz-row">
                        <div>
                            <label for="m-callsign">To</label>
                            <input type="text" id="m-callsign" placeholder="Everyone, or a callsign">
                        </div>
                        <div>
                            <label for="m-server">Server</label>
                            <select id="m-server">
                                <option value="all">All servers</option>
                                <option value="live" selected>Live VATSIM</option>
                                <option value="sb1">SweatBox 1</option>
                                <option value="sb2">SweatBox 2</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="m-title">Title</label>
                        <input type="text" id="m-title" maxlength="120" required>
                    </div>

                    <div>
                        <label for="m-body">Message</label>
                        <textarea id="m-body" maxlength="2000" required></textarea>
                    </div>

                    <div class="oz-row">
                        <div>
                            <label for="m-from">From</label>
                            <input type="text" id="m-from" maxlength="40" placeholder="Your name" required>
                        </div>
                        <div>
                            <label for="m-expires">Show for</label>
                            <select id="m-expires">
                                <option value="15">15 minutes</option>
                                <option value="60">1 hour</option>
                                <option value="120" selected>2 hours</option>
                                <option value="360">6 hours</option>
                                <option value="1440">1 day</option>
                                <option value="10080">1 week</option>
                            </select>
                        </div>
                    </div>

                    <label class="oz-check"><input type="checkbox" id="m-reply"> Let controllers reply</label>

                    <button type="submit" class="oz-send" id="m-send">Send</button>
                    <div class="oz-status" id="m-status"></div>
                </form>

                <div class="oz-sent">
                    <h2>Sent - last 7 days</h2>
                    <div id="sent-list"><div class="oz-empty">Loading…</div></div>
                </div>
            </div>
        </div>

        <script>
            const apiBaseUrl = @json(rtrim(config('services.ozserver_api.url'), '/'));
            const INITIALLY_UNLOCKED = @json($unlocked);
            const MESSAGING_ENABLED = @json($messagingEnabled);
            const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
            const POLL_INTERVAL_MS = 2000;
            const POLL_WINDOW = '2 minutes';
            const MAX_LINES = 2000;
            const MESSAGES_POLL_MS = 10000;

            // Calls this site's own /console routes: JSON both ways, with the CSRF token and the
            // session cookie (same origin).
            async function consoleFetch(path, options = {}) {
                const response = await fetch(path, {
                    ...options,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        ...(options.headers || {}),
                    },
                });
                let body = null;
                try { body = await response.json(); } catch { body = null; }
                return { ok: response.ok, status: response.status, body };
            }

            // --- Password gate. Checked on the server (POST /console/unlock) and remembered in the
            // session, so the password is not in this page and the message routes can trust it.
            const gate = document.getElementById('gate');
            const app = document.getElementById('app');
            let started = false;

            function unlock() {
                gate.hidden = true;
                app.hidden = false;
                if (!started) {
                    started = true;
                    startConsole();
                    if (MESSAGING_ENABLED) startMessages();
                }
            }

            async function tryUnlock() {
                const password = document.getElementById('gate-password').value;
                const result = await consoleFetch('/console/unlock', { method: 'POST', body: JSON.stringify({ password }) });
                if (result.ok) {
                    unlock();
                } else {
                    const error = document.getElementById('gate-error');
                    error.textContent = result.status === 429 ? 'Too many attempts - wait a minute.' : 'Incorrect password.';
                    error.hidden = false;
                }
            }

            document.getElementById('gate-submit').addEventListener('click', tryUnlock);
            document.getElementById('gate-password').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') tryUnlock();
            });
            document.getElementById('lock').addEventListener('click', async () => {
                await consoleFetch('/console/lock', { method: 'POST' });
                window.location.reload();
            });

            // Logs / Messages.
            const tabLogs = document.getElementById('tab-logs');
            const tabMessages = document.getElementById('tab-messages');
            const logControls = document.getElementById('log-controls');
            const logView = document.getElementById('log');
            const messagesView = document.getElementById('messages');
            const countView = document.getElementById('count');
            let onMessagesShown = null;

            function showTab(name) {
                const messages = name === 'messages';
                tabLogs.classList.toggle('active', !messages);
                tabMessages.classList.toggle('active', messages);
                logControls.hidden = messages;
                logView.hidden = messages;
                countView.hidden = messages;
                messagesView.hidden = !messages;
                if (messages && onMessagesShown) onMessagesShown();
            }
            tabLogs.addEventListener('click', () => showTab('logs'));
            tabMessages.addEventListener('click', () => showTab('messages'));

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

            // --- Developer messages: send to controllers' plugins, and see who read and replied.
            // Everything goes through this site's /console/api routes, which hold the API token.
            function startMessages() {
                const form = document.getElementById('compose');
                const status = document.getElementById('m-status');
                const sendButton = document.getElementById('m-send');
                const list = document.getElementById('sent-list');
                const fromInput = document.getElementById('m-from');
                const expanded = new Set();

                fromInput.value = localStorage.getItem('oz-console-from') || '';

                function text(value) {
                    const div = document.createElement('div');
                    div.textContent = value == null ? '' : String(value);
                    return div.innerHTML;
                }

                function time(iso) {
                    return iso ? new Date(iso).toISOString().slice(0, 16).replace('T', ' ') + 'z' : '';
                }

                function setStatus(message, kind) {
                    status.textContent = message;
                    status.className = 'oz-status' + (kind ? ' ' + kind : '');
                }

                function render(messages) {
                    if (!Array.isArray(messages) || messages.length === 0) {
                        list.innerHTML = '<div class="oz-empty">No messages sent in the last 7 days.</div>';
                        return;
                    }

                    const now = Date.now();
                    list.innerHTML = '';
                    for (const m of messages) {
                        const expired = new Date(m.expires_at).getTime() < now;
                        const read = m.receipts.filter(r => r.read_at).length;
                        const card = document.createElement('div');
                        card.className = 'oz-msg' + (m.withdrawn_at ? ' withdrawn' : expired ? ' expired' : '');

                        // Every field here was typed by someone - into this console or, for
                        // replies, into a plugin - so all of it is escaped.
                        const to = m.callsign ? text(m.callsign) : 'everyone';
                        const server = m.server ? text(m.server) : 'all servers';
                        card.innerHTML = `
                            <div class="oz-msg-head">
                                <span class="oz-msg-title">${text(m.title)}</span>
                                ${m.allow_reply ? '<span class="oz-badge reply">replies on</span>' : ''}
                                ${m.withdrawn_at ? '<span class="oz-badge withdrawn">withdrawn</span>' : expired ? '<span class="oz-badge expired">expired</span>' : ''}
                                ${!m.withdrawn_at && !expired ? '<button class="oz-withdraw">Withdraw</button>' : ''}
                            </div>
                            <div class="oz-msg-meta">To ${to} on ${server} · from ${text(m.sent_by)} · ${time(m.created_at)} · until ${time(m.expires_at)}</div>
                            <div class="oz-msg-body">${text(m.body)}</div>
                            <div class="oz-msg-stats">Delivered to ${m.receipts.length} · read by ${read} · ${m.replies.length} repl${m.replies.length === 1 ? 'y' : 'ies'} ${m.receipts.length ? (expanded.has(m.id) ? '▾' : '▸') : ''}</div>
                            ${expanded.has(m.id) ? `<div class="oz-msg-receipts">${m.receipts.map(r =>
                                `${text(r.callsign)} (${text(r.server)}) - delivered ${time(r.delivered_at)}${r.read_at ? ', read ' + time(r.read_at) : ', not read yet'}`).join('<br>')}</div>` : ''}
                            ${m.replies.map(r => `
                                <div class="oz-reply">
                                    <div class="who">${text(r.callsign)} (${text(r.server)}) · ${time(r.created_at)}</div>
                                    <div class="text">${text(r.body)}</div>
                                </div>`).join('')}
                        `;

                        card.querySelector('.oz-msg-stats').addEventListener('click', () => {
                            if (expanded.has(m.id)) expanded.delete(m.id); else expanded.add(m.id);
                            render(messages);
                        });

                        const withdraw = card.querySelector('.oz-withdraw');
                        if (withdraw) {
                            withdraw.addEventListener('click', async () => {
                                if (!confirm(`Withdraw "${m.title}"? Controllers who have not had it yet will not get it.`)) return;
                                const result = await consoleFetch(`/console/api/messages/${m.id}/withdraw`, { method: 'POST' });
                                if (!result.ok) alert(result.body?.message || `Could not withdraw (HTTP ${result.status}).`);
                                load();
                            });
                        }

                        list.appendChild(card);
                    }
                }

                async function load() {
                    const result = await consoleFetch('/console/api/messages');
                    if (result.status === 401) {
                        window.location.reload();
                        return;
                    }
                    if (!result.ok) {
                        list.innerHTML = `<div class="oz-empty">${text(result.body?.message || 'Could not load messages.')}</div>`;
                        return;
                    }
                    render(result.body);
                }

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const callsign = document.getElementById('m-callsign').value.trim();
                    const server = document.getElementById('m-server').value;
                    const payload = {
                        title: document.getElementById('m-title').value.trim(),
                        body: document.getElementById('m-body').value.trim(),
                        callsign: callsign || null,
                        server,
                        allow_reply: document.getElementById('m-reply').checked,
                        expires_minutes: Number(document.getElementById('m-expires').value),
                        sent_by: fromInput.value.trim(),
                    };

                    const audience = `${callsign || 'everyone'} on ${server === 'all' ? 'all servers' : server}`;
                    if (!confirm(`Send "${payload.title}" to ${audience}?`)) return;

                    sendButton.disabled = true;
                    setStatus('Sending…');
                    const result = await consoleFetch('/console/api/messages', { method: 'POST', body: JSON.stringify(payload) });
                    sendButton.disabled = false;

                    if (result.ok) {
                        localStorage.setItem('oz-console-from', payload.sent_by);
                        document.getElementById('m-title').value = '';
                        document.getElementById('m-body').value = '';
                        setStatus(`Sent to ${audience}.`, 'ok');
                        load();
                    } else {
                        const errors = result.body?.errors ? Object.values(result.body.errors).flat().join(' ') : '';
                        setStatus(errors || result.body?.message || `Could not send (HTTP ${result.status}).`, 'err');
                    }
                });

                // Refreshed only while the tab is on screen.
                let timer = null;
                onMessagesShown = () => {
                    load();
                    if (!timer) timer = setInterval(() => { if (!messagesView.hidden) load(); }, MESSAGES_POLL_MS);
                };
            }

            if (INITIALLY_UNLOCKED) unlock();
        </script>
    </body>
</html>
