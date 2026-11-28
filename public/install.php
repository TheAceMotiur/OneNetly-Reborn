<?php

declare(strict_types=1);

use App\Support\Installer\Installer;
use App\Support\Installer\InstallerAuth;

/**
 * OneNetly installer / updater.
 *
 * First run (no APP_KEY): installs the application.
 * Every later run: only updates it, behind an administrator login.
 *
 * Standalone: it does not boot Laravel until a step needs Artisan.
 */
$basePath = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($basePath): void {
    $prefix = 'App\\Support\\Installer\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $file = $basePath.'/app/Support/Installer/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($file)) {
        require_once $file;
    }
});

$installer = new Installer($basePath);
$auth = new InstallerAuth($installer, $basePath);

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');

    $payload = [];
    $raw = file_get_contents('php://input');

    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    $respond = static function (array $data, int $status = 200): never {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    };

    $token = (string) ($_SERVER['HTTP_X_INSTALL_TOKEN'] ?? (is_string($payload['token'] ?? null) ? $payload['token'] : ''));
    $step = is_string($payload['step'] ?? null) ? (string) $payload['step'] : '';
    $email = is_string($payload['email'] ?? null) ? $payload['email'] : '';
    $password = is_string($payload['password'] ?? null) ? $payload['password'] : '';

    try {
        switch ($action) {
            case 'status':
                $status = $installer->status();
                $status['lockedFor'] = $auth->lockedFor();
                $status['hasSession'] = $auth->check($token);

                $respond(['ok' => true, 'status' => $status]);

                // no break: respond() exits
            case 'login':
                $result = $auth->attempt($email, $password);

                $respond($result, $result['ok'] ? 200 : 401);

            case 'logout':
                $auth->revoke($token);
                $respond(['ok' => true, 'message' => 'Signed out.']);

            case 'install':
                if ($installer->mode() !== Installer::MODE_INSTALL) {
                    $respond(['ok' => false, 'message' => 'This application is already installed. Reload the page for update mode.'], 409);
                }

                $respond(['ok' => true] + $installer->run(Installer::MODE_INSTALL, $step, $payload));

            case 'update':
                if (! $auth->check($token)) {
                    $respond(['ok' => false, 'message' => 'Sign in as an administrator to run updates.'], 401);
                }

                if ($installer->mode() !== Installer::MODE_UPDATE) {
                    $respond(['ok' => false, 'message' => 'This application has not been installed yet.'], 409);
                }

                $respond(['ok' => true] + $installer->run(Installer::MODE_UPDATE, $step));

            case 'cancel':
                if (! $auth->check($token)) {
                    $respond(['ok' => false, 'message' => 'Sign in as an administrator to change this.'], 401);
                }

                $respond(['ok' => true] + $installer->cancelMaintenance());

            default:
                $respond(['ok' => false, 'message' => 'Unknown action.'], 404);
        }
    } catch (Throwable $exception) {
        $installer->log('error: '.$exception->getMessage());

        $respond(['ok' => false, 'message' => $exception->getMessage()], 422);
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

echo <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>OneNetly · Installer</title>
<style>
:root {
    --bg: #070a12;
    --panel: rgba(17, 24, 39, .82);
    --line: rgba(148, 163, 184, .14);
    --line-strong: rgba(148, 163, 184, .26);
    --text: #e8edf7;
    --muted: #93a1b8;
    --dim: #64748b;
    --accent: #6366f1;
    --accent-2: #8b5cf6;
    --ok: #34d399;
    --warn: #fbbf24;
    --bad: #f87171;
    --radius: 18px;
}

* { box-sizing: border-box; }

html, body { height: 100%; }

body {
    margin: 0;
    background:
        radial-gradient(1100px 600px at 12% -10%, rgba(99, 102, 241, .20), transparent 60%),
        radial-gradient(900px 520px at 92% 0%, rgba(139, 92, 246, .14), transparent 62%),
        var(--bg);
    color: var(--text);
    font: 15px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
    padding: 34px 20px 64px;
}

.shell { width: 100%; max-width: 880px; margin: 0 auto; }

.topbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; margin-bottom: 22px;
}

.brand { display: flex; align-items: center; gap: 12px; font-weight: 650; letter-spacing: .2px; }

.mark {
    display: grid; place-items: center; width: 34px; height: 34px;
    border-radius: 11px; font-size: 16px; color: #fff;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    box-shadow: 0 10px 30px rgba(99, 102, 241, .35);
}

.brand .dim { color: var(--dim); font-weight: 500; }

.pill {
    padding: 6px 13px; border-radius: 999px; font-size: 12.5px; font-weight: 600;
    letter-spacing: .3px; text-transform: uppercase;
    border: 1px solid var(--line-strong);
    color: var(--muted); background: rgba(148, 163, 184, .07);
    white-space: nowrap;
}

.pill[data-mode="install"] { color: #c7d2fe; border-color: rgba(99, 102, 241, .45); background: rgba(99, 102, 241, .14); }
.pill[data-mode="update"] { color: #ddd6fe; border-color: rgba(139, 92, 246, .45); background: rgba(139, 92, 246, .14); }
.pill[data-mode="ready"] { color: #bbf7d0; border-color: rgba(52, 211, 153, .42); background: rgba(52, 211, 153, .13); }

.card {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 30px 32px 32px;
    backdrop-filter: blur(14px);
    box-shadow: 0 30px 70px rgba(0, 0, 0, .45), inset 0 1px 0 rgba(255, 255, 255, .04);
}

h1 { margin: 0 0 6px; font-size: 25px; line-height: 1.25; letter-spacing: -.4px; font-weight: 650; }
h2 { margin: 26px 0 12px; font-size: 15px; letter-spacing: .2px; }
.lede { margin: 0 0 24px; color: var(--muted); font-size: 14.5px; }

fieldset {
    margin: 0 0 20px; padding: 18px 18px 6px;
    border: 1px solid var(--line); border-radius: 14px;
    background: rgba(2, 6, 23, .35);
}

legend {
    padding: 0 8px; margin-left: -4px; font-size: 12px; font-weight: 650;
    letter-spacing: .9px; text-transform: uppercase; color: var(--dim);
}

.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.grid .full { grid-column: 1 / -1; }

.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; min-width: 0; }
.field > span { font-size: 12.5px; font-weight: 600; color: var(--muted); letter-spacing: .2px; }

input, select {
    width: 100%; padding: 10px 12px; font: inherit; font-size: 14px;
    color: var(--text); background: rgba(2, 6, 23, .72);
    border: 1px solid var(--line-strong); border-radius: 10px;
    transition: border-color .15s, box-shadow .15s;
}

input::placeholder { color: #4b5a72; }

input:focus, select:focus {
    outline: none; border-color: rgba(99, 102, 241, .75);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, .18);
}

.hint { margin: -6px 0 12px; color: var(--dim); font-size: 12.5px; }

button {
    font: inherit; font-weight: 600; cursor: pointer;
    border-radius: 11px; border: 1px solid transparent;
    padding: 11px 20px; transition: transform .12s, filter .15s, opacity .15s;
}

button:active { transform: translateY(1px); }
button[disabled] { opacity: .55; cursor: not-allowed; }

.primary {
    width: 100%; padding: 13px 22px; color: #fff;
    background: linear-gradient(135deg, var(--accent), var(--accent-2));
    box-shadow: 0 14px 34px rgba(99, 102, 241, .32);
}

.primary:hover:not([disabled]) { filter: brightness(1.08); }

.ghost {
    color: var(--text); background: rgba(148, 163, 184, .08);
    border-color: var(--line-strong);
}

.ghost:hover:not([disabled]) { background: rgba(148, 163, 184, .14); }

.actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 6px; }
.actions .primary { width: auto; }
.actions .spacer { flex: 1; }

.meta { display: flex; flex-wrap: wrap; gap: 10px 22px; color: var(--muted); font-size: 13px; margin-bottom: 4px; }
.meta b { color: var(--text); font-weight: 600; }

.note {
    display: flex; gap: 10px; padding: 13px 15px; margin: 18px 0 0;
    border-radius: 12px; font-size: 13.5px; line-height: 1.55;
    border: 1px solid var(--line); background: rgba(148, 163, 184, .06); color: var(--muted);
}

.note.warn { border-color: rgba(251, 191, 36, .32); background: rgba(251, 191, 36, .09); color: #fde68a; }
.note.bad { border-color: rgba(248, 113, 113, .35); background: rgba(248, 113, 113, .1); color: #fecaca; }
.note.ok { border-color: rgba(52, 211, 153, .32); background: rgba(52, 211, 153, .09); color: #a7f3d0; }

.steps { list-style: none; margin: 20px 0 22px; padding: 0; display: grid; gap: 2px; }

.step {
    display: flex; gap: 13px; align-items: flex-start;
    padding: 11px 12px; border-radius: 11px; border: 1px solid transparent;
    transition: background .15s, border-color .15s;
}

.step .dot {
    flex: none; width: 20px; height: 20px; margin-top: 1px; border-radius: 50%;
    border: 2px solid var(--line-strong); position: relative;
    display: grid; place-items: center; font-size: 11px; color: var(--bg); font-weight: 800;
}

.step strong { display: block; font-size: 13.8px; font-weight: 600; }
.step small { display: block; color: var(--dim); font-size: 12.3px; }

.step[data-state="running"] { background: rgba(251, 191, 36, .07); border-color: rgba(251, 191, 36, .22); }
.step[data-state="running"] .dot { border-color: var(--warn); animation: pulse 1.1s ease-in-out infinite; }
.step[data-state="done"] .dot { border-color: var(--ok); background: var(--ok); }
.step[data-state="done"] .dot::after { content: "✓"; }
.step[data-state="failed"] .dot { border-color: var(--bad); background: var(--bad); }
.step[data-state="failed"] .dot::after { content: "!"; }
.step[data-state="skipped"] .dot { border-color: var(--line-strong); background: var(--line-strong); }

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, .45); }
    50% { box-shadow: 0 0 0 6px rgba(251, 191, 36, 0); }
}

.log {
    margin: 0 0 18px; padding: 14px 16px; max-height: 260px; overflow: auto;
    background: #04070e; border: 1px solid var(--line); border-radius: 12px;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    font-size: 12.4px; line-height: 1.6; color: #c6d2e4; white-space: pre-wrap; word-break: break-word;
}

.log .ok { color: #86efac; }
.log .error { color: #fca5a5; }
.log .muted { color: #64748b; }

.center { text-align: center; padding: 26px 0 10px; }

.badge-icon {
    display: grid; place-items: center; width: 62px; height: 62px; margin: 0 auto 16px;
    border-radius: 50%; font-size: 27px;
    background: rgba(52, 211, 153, .14); border: 1px solid rgba(52, 211, 153, .4); color: var(--ok);
}

.badge-icon.bad { background: rgba(248, 113, 113, .13); border-color: rgba(248, 113, 113, .4); color: var(--bad); }

.links { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 20px; }
.links a {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
    border-radius: 11px; text-decoration: none; font-weight: 600; font-size: 14px;
    color: var(--text); background: rgba(148, 163, 184, .1); border: 1px solid var(--line-strong);
}
.links a.primary-link { color: #fff; background: linear-gradient(135deg, var(--accent), var(--accent-2)); border-color: transparent; }
.links a:hover { filter: brightness(1.1); }

footer { margin-top: 18px; text-align: center; color: #475569; font-size: 12.3px; }

@media (max-width: 620px) {
    body { padding: 22px 14px 48px; }
    .card { padding: 22px 18px 24px; border-radius: 15px; }
    .grid { grid-template-columns: 1fr; }
    h1 { font-size: 22px; }
}
</style>
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div class="brand"><span class="mark">◆</span> OneNetly <span class="dim">Installer</span></div>
        <span class="pill" id="modePill" data-mode="install">Checking…</span>
    </div>

    <main class="card">
        <div id="loadingView" class="center">
            <p class="lede" style="margin:0">Reading the current installation…</p>
        </div>

        <section id="installView" hidden>
            <h1>Set up OneNetly</h1>
            <p class="lede">This is a first run, so the installer will create the database tables, the plans and your administrator account.</p>

            <form id="installForm" novalidate>
                <fieldset>
                    <legend>Site</legend>
                    <div class="grid">
                        <label class="field"><span>Site name</span><input name="app_name" value="OneNetly" autocomplete="organization" required></label>
                        <label class="field"><span>Site URL</span><input name="app_url" id="appUrl" placeholder="https://example.com" autocomplete="url" required></label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Database</legend>
                    <div class="grid">
                        <label class="field"><span>Driver</span>
                            <select name="db_connection" id="dbDriver">
                                <option value="mysql">MySQL / MariaDB</option>
                                <option value="pgsql">PostgreSQL</option>
                                <option value="sqlite">SQLite</option>
                            </select>
                        </label>
                        <div class="field" id="dbPortField"><span>Port</span><input name="db_port" value="3306" inputmode="numeric"></div>
                    </div>

                    <div id="dbNetwork">
                        <div class="grid">
                            <label class="field"><span>Host</span><input name="db_host" value="localhost" autocomplete="off"></label>
                            <label class="field"><span>Database name</span><input name="db_database" placeholder="hestiauser_onenetly" autocomplete="off"></label>
                            <label class="field"><span>Username</span><input name="db_username" autocomplete="off"></label>
                            <label class="field"><span>Password</span><input name="db_password" type="password" autocomplete="new-password"></label>
                        </div>
                        <p class="hint">HestiaCP shows these under Databases. The database and the user must already exist.</p>
                    </div>

                    <div id="dbSqlite" hidden>
                        <label class="field"><span>SQLite file</span><input name="sqlite_path" value="database/database.sqlite" autocomplete="off"></label>
                        <p class="hint">A relative path is resolved from the project root. The file is created when missing.</p>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Administrator</legend>
                    <div class="grid">
                        <label class="field"><span>Name</span><input name="admin_name" placeholder="Your name" autocomplete="name" required></label>
                        <label class="field"><span>Email</span><input name="admin_email" type="email" placeholder="you@example.com" autocomplete="email" required></label>
                        <label class="field full"><span>Password</span><input name="admin_password" type="password" autocomplete="new-password" required></label>
                    </div>
                    <p class="hint">At least 12 characters, with an upper case letter, a lower case letter and a number. This account also unlocks update mode.</p>
                </fieldset>

                <div id="installError" class="note bad" hidden></div>

                <button class="primary" type="submit" id="installButton">Install now</button>
            </form>
        </section>

        <section id="loginView" hidden>
            <h1>Administrator sign-in</h1>
            <p class="lede">This application is already installed, so running <code>install.php</code> again only updates it. Sign in with an administrator account to continue.</p>

            <form id="loginForm" novalidate>
                <div class="grid">
                    <label class="field"><span>Email</span><input name="email" type="email" autocomplete="username" required></label>
                    <label class="field"><span>Password</span><input name="password" type="password" autocomplete="current-password" required></label>
                </div>
                <div id="loginError" class="note bad" hidden></div>
                <button class="primary" type="submit" id="loginButton">Sign in</button>
            </form>

            <div class="note">Update mode changes files and the database, so it is restricted to administrators. Five failed attempts lock this page for ten minutes.</div>
        </section>

        <section id="updateView" hidden>
            <h1>Update OneNetly</h1>
            <p class="lede">Upload your changed files first, then run the update. A database backup is taken before anything changes.</p>

            <div class="meta">
                <span>Site <b id="updateName">—</b></span>
                <span>URL <b id="updateUrl">—</b></span>
                <span>Last update <b id="updateLast">never</b></span>
                <span>Signed in as <b id="updateUser">admin</b></span>
            </div>

            <h2>Detected changes</h2>
            <ul id="changeList" class="steps" style="margin-top:8px"></ul>

            <div id="upToDateNote" class="note ok" hidden>Everything matches your last update. Running it again is safe and will simply find nothing to migrate.</div>
            <div id="updateError" class="note bad" hidden></div>

            <div class="actions">
                <button class="primary" type="button" id="updateButton">Run update</button>
                <button class="ghost" type="button" id="refreshButton">Check again</button>
                <span class="spacer"></span>
                <button class="ghost" type="button" id="logoutButton">Sign out</button>
            </div>

            <div class="note">Keep <code>install.php</code> on the server: it is your update button. You can also run <code>php artisan app:update</code> over SSH.</div>
        </section>

        <section id="runView" hidden>
            <h1 id="runTitle">Working…</h1>
            <p class="lede" id="runLede">Keep this tab open until every step is finished.</p>
            <ol class="steps" id="stepList"></ol>
            <pre class="log" id="log"></pre>
            <div class="actions" id="runActions" hidden>
                <button class="primary" type="button" id="retryButton" hidden>Fix and reload</button>
                <button class="ghost" type="button" id="cancelButton" hidden>Turn maintenance mode off</button>
                <button class="ghost" type="button" id="backButton">Back</button>
            </div>
        </section>

        <section id="doneView" hidden>
            <div class="center">
                <div class="badge-icon" id="doneIcon">✓</div>
                <h1 id="doneTitle">All done</h1>
                <p class="lede" id="doneText" style="margin-bottom:0"></p>
            </div>
            <div class="links" id="doneLinks"></div>
        </section>
    </main>

    <footer>OneNetly installer · install once, update any time</footer>
</div>

<script>
(function () {
    'use strict';

    const state = {
        mode: 'install',
        token: sessionStorage.getItem('installer.token') || '',
        status: null,
        steps: [],
    };

    const $ = (id) => document.getElementById(id);

    const views = ['loadingView', 'installView', 'loginView', 'updateView', 'runView', 'doneView'];

    function show(id) {
        views.forEach((view) => { $(view).hidden = view !== id; });
    }

    function setPill(text, mode) {
        const pill = $('modePill');
        pill.textContent = text;
        pill.dataset.mode = mode;
    }

    function showError(element, message) {
        element.textContent = message;
        element.hidden = !message;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    async function api(action, body) {
        const response = await fetch(location.pathname + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Install-Token': state.token},
            body: JSON.stringify(body || {}),
            cache: 'no-store',
            credentials: 'same-origin',
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            data = {ok: false, message: text.trim().slice(0, 1200) || 'The server returned an unexpected response (HTTP ' + response.status + ').'};
        }

        if (response.status === 401 && action !== 'login') {
            state.token = '';
            sessionStorage.removeItem('installer.token');
        }

        return data;
    }

    function logLine(text, className) {
        const log = $('log');
        const span = document.createElement('span');

        if (className) {
            span.className = className;
        }

        span.textContent = text;

        log.appendChild(span);
        log.appendChild(document.createTextNode('\n'));
        log.scrollTop = log.scrollHeight;
    }

    function renderSteps() {
        $('stepList').innerHTML = state.steps.map((step) =>
            '<li class="step" data-id="' + escapeHtml(step.id) + '" data-state="pending">' +
            '<span class="dot"></span>' +
            '<div><strong>' + escapeHtml(step.label) + '</strong><small>' + escapeHtml(step.description) + '</small></div>' +
            '</li>'
        ).join('');
    }

    function setStep(id, newState) {
        const node = document.querySelector('.step[data-id="' + id + '"]');

        if (node) {
            node.dataset.state = newState;
        }
    }

    function payloadFromForm(form) {
        const data = Object.fromEntries(new FormData(form).entries());

        if (data.db_connection === 'sqlite') {
            data.db_database = data.sqlite_path || 'database/database.sqlite';
            data.db_host = '';
            data.db_port = '';
            data.db_username = '';
            data.db_password = '';
        }

        return data;
    }

    async function loadStatus() {
        const data = await api('status');

        if (!data.ok) {
            show('loadingView');
            $('loadingView').innerHTML = '<div class="note bad">' + escapeHtml(data.message || 'Could not read the installer status.') + '</div>';
            return;
        }

        state.status = data.status;
        state.mode = data.status.mode;
        state.steps = data.status.steps;

        if (state.mode === 'install') {
            setPill('Install mode', 'install');
            show('installView');

            if (!$('appUrl').value && !/^(localhost|127\.)/.test(location.hostname)) {
                $('appUrl').value = location.origin;
            }

            return;
        }

        if (data.status.upToDate) {
            setPill('Up to date', 'ready');
        } else {
            setPill('Update available', 'update');
        }

        if (state.token && data.status.hasSession) {
            showUpdate();
        } else {
            state.token = '';
            sessionStorage.removeItem('installer.token');
            show('loginView');
        }
    }

    function showUpdate() {
        const status = state.status;
        show('updateView');
        $('updateName').textContent = status.appName || 'OneNetly';
        $('updateUrl').textContent = status.appUrl || '—';
        $('updateLast').textContent = status.lastUpdate ? new Date(status.lastUpdate).toLocaleString() : 'never';
        $('updateUser').textContent = sessionStorage.getItem('installer.email') || 'administrator';
        $('upToDateNote').hidden = !status.upToDate;
        $('updateButton').disabled = false;
        showError($('updateError'), '');

        $('changeList').innerHTML = (status.changes || []).map((change) =>
            '<li class="step"><span class="dot" style="border-color:rgba(148,163,184,.5)"></span><div><strong>' +
            escapeHtml(change) + '</strong></div></li>'
        ).join('');
    }

    async function runSequence(mode, payload) {
        state.steps = state.status.steps;
        renderSteps();
        $('log').textContent = '';
        $('runTitle').textContent = mode === 'install' ? 'Installing OneNetly' : 'Updating OneNetly';
        $('runLede').textContent = mode === 'install'
            ? 'Keep this tab open. Each step runs in its own request so slow migrations cannot time out.'
            : 'The site is briefly in maintenance mode. Keep this tab open until it is back online.';
        $('runActions').hidden = true;
        $('retryButton').hidden = true;
        $('cancelButton').hidden = true;
        show('runView');

        let failure = null;

        for (let index = 0; index < state.steps.length; index++) {
            const step = state.steps[index];
            setStep(step.id, 'running');
            logLine('\n▸ ' + step.label + ' (' + (index + 1) + '/' + state.steps.length + ')', 'muted');

            const data = await api(mode === 'install' ? 'install' : 'update', Object.assign({}, payload || {}, {step: step.id}));

            if (!data.ok) {
                setStep(step.id, 'failed');
                logLine('✖ ' + (data.message || 'This step failed.'), 'error');
                failure = {step: step, message: data.message || 'This step failed.'};
                break;
            }

            setStep(step.id, 'done');

            if (data.message) {
                logLine('✔ ' + data.message, 'ok');
            }

            if (data.output) {
                logLine(data.output.replace(/^/gm, '   '));
            }
        }

        if (failure) {
            $('runActions').hidden = false;
            $('backButton').hidden = true;
            $('retryButton').hidden = false;
            $('cancelButton').hidden = mode !== 'update';
            $('runTitle').textContent = mode === 'install' ? 'Installation stopped' : 'Update stopped';
            $('runLede').textContent = failure.message;

            if (mode === 'update') {
                logLine('\nThe site may still be in maintenance mode. Use "Turn maintenance mode off" to bring it back.', 'muted');
            }

            return;
        }

        finish(mode);
    }

    function finish(mode) {
        const url = (state.status.appUrl || '').replace(/\/$/, '');

        show('doneView');
        setPill(mode === 'install' ? 'Installed' : 'Updated', 'ready');
        $('doneIcon').className = 'badge-icon';
        $('doneIcon').textContent = '✓';

        if (mode === 'install') {
            $('doneTitle').textContent = 'OneNetly is installed';
            $('doneText').textContent = 'Your administrator account is ready. Open the site or sign in to the admin panel — install.php now only performs updates, behind an administrator login.';
            $('doneLinks').innerHTML =
                (url ? '<a class="primary-link" href="' + escapeHtml(url) + '">Open site</a>' : '') +
                (url ? '<a href="' + escapeHtml(url + '/admin') + '">Admin panel</a>' : '');
        } else {
            $('doneTitle').textContent = 'Update complete';
            $('doneText').textContent = 'Migrations are applied, caches are rebuilt and the site is back online.';
            $('doneLinks').innerHTML = url ? '<a class="primary-link" href="' + escapeHtml(url) + '">Open site</a>' : '';
        }
    }

    $('dbDriver').addEventListener('change', function () {
        const sqlite = this.value === 'sqlite';
        $('dbNetwork').hidden = sqlite;
        $('dbSqlite').hidden = !sqlite;
        $('dbPortField').hidden = sqlite;
        $('dbPortField').querySelector('input').value = this.value === 'pgsql' ? '5432' : '3306';
    });

    $('installForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        showError($('installError'), '');

        const payload = payloadFromForm(this);

        if (!payload.app_name || !payload.app_url) {
            showError($('installError'), 'Enter the site name and the full site URL.');
            return;
        }

        if (!payload.admin_name || !payload.admin_email || !payload.admin_password) {
            showError($('installError'), 'Enter the administrator name, email and password.');
            return;
        }

        state.status = state.status || {};
        $('installButton').disabled = true;
        await runSequence('install', payload);
    });

    $('loginForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        showError($('loginError'), '');

        const data = Object.fromEntries(new FormData(this).entries());
        $('loginButton').disabled = true;

        const result = await api('login', data);
        $('loginButton').disabled = false;

        if (!result.ok) {
            showError($('loginError'), result.message || 'Sign-in failed.');
            return;
        }

        state.token = result.token;
        sessionStorage.setItem('installer.token', result.token);
        sessionStorage.setItem('installer.email', data.email);
        this.reset();
        await loadStatus();
    });

    $('updateButton').addEventListener('click', async function () {
        this.disabled = true;
        state.status = state.status || {};
        await runSequence('update', {});
    });

    $('refreshButton').addEventListener('click', loadStatus);

    $('logoutButton').addEventListener('click', async function () {
        await api('logout');
        state.token = '';
        sessionStorage.removeItem('installer.token');
        sessionStorage.removeItem('installer.email');
        location.reload();
    });

    $('cancelButton').addEventListener('click', async function () {
        this.disabled = true;
        const data = await api('cancel');
        this.disabled = false;
        logLine('\n' + (data.message || (data.ok ? 'Maintenance mode is off.' : 'Could not turn maintenance mode off.')), data.ok ? 'ok' : 'error');
    });

    $('retryButton').addEventListener('click', () => location.reload());
    $('backButton').addEventListener('click', () => location.reload());

    loadStatus();
})();
</script>
</body>
</html>
HTML;
