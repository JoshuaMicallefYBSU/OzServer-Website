<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

// The /console page, and the developer messages it sends to controllers' plugins.
//
// The password is checked here, on the server, and remembered in the session. Messages go to the
// OzServer API's /admin/messages routes with the API's CONSOLE_TOKEN, which only this server holds:
// putting text into every controller's vatSys has to be gated by something the browser cannot read.
// (The live log tail still reads GET /api/v1/client-logs straight from the browser - that endpoint
// is public in any case.)
class ConsoleController extends Controller
{
    // Every developer message is signed this way.
    private const SENDER = 'OzServer Development Team';

    public function show(Request $request): View
    {
        abort_if(blank(config('services.ozserver_api.console_password')), 404);

        return view('console', [
            'unlocked' => $request->session()->get('console_unlocked') === true,
            'messagingEnabled' => filled(config('services.ozserver_api.console_token')),
        ]);
    }

    public function unlock(Request $request): JsonResponse
    {
        $expected = (string) config('services.ozserver_api.console_password');
        abort_if($expected === '', 404);

        $given = (string) $request->input('password', '');
        if (! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Incorrect password.'], 401);
        }

        // A fresh session id for the unlocked session.
        $request->session()->regenerate();
        $request->session()->put('console_unlocked', true);

        return response()->json(['unlocked' => true]);
    }

    public function lock(Request $request): JsonResponse
    {
        $request->session()->forget('console_unlocked');

        return response()->json(['unlocked' => false]);
    }

    public function messages(Request $request): JsonResponse
    {
        $this->ensureUnlocked($request);

        return $this->forward(fn (PendingRequest $api) => $api->get('/api/v1/admin/messages', [
            'days' => (int) $request->query('days', 7),
        ]));
    }

    public function send(Request $request): JsonResponse
    {
        $this->ensureUnlocked($request);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'server' => ['nullable', 'in:all,live,sb1,sb2'],
            // Empty: everyone. Otherwise the connected controllers picked from the checklist.
            'callsigns' => ['nullable', 'array', 'max:100'],
            'callsigns.*' => ['string', 'max:32'],
            'allow_reply' => ['boolean'],
            // Only for a message to everyone; one to chosen controllers is theirs alone.
            'expires_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
        ]);

        return $this->forward(fn (PendingRequest $api) => $api->post('/api/v1/admin/messages', [
            'title' => (string) ($data['title'] ?? ''),
            'body' => $data['body'],
            'server' => $data['server'] ?? 'all',
            'callsigns' => array_values(array_filter($data['callsigns'] ?? [], 'filled')) ?: null,
            'allow_reply' => (bool) ($data['allow_reply'] ?? true),
            'expires_minutes' => (int) ($data['expires_minutes'] ?? 15),
            'sent_by' => self::SENDER,
        ]));
    }

    // Controllers connected now, for the To checklist.
    public function clients(Request $request): JsonResponse
    {
        $this->ensureUnlocked($request);

        $server = (string) $request->query('server', 'all');
        abort_unless(in_array($server, ['all', 'live', 'sb1', 'sb2'], true), 422, 'Unknown server.');

        return $this->forward(fn (PendingRequest $api) => $api->get('/api/v1/admin/clients', ['server' => $server]));
    }

    public function withdraw(Request $request, int $id): JsonResponse
    {
        $this->ensureUnlocked($request);

        return $this->forward(fn (PendingRequest $api) => $api->post("/api/v1/admin/messages/{$id}/withdraw"));
    }

    private function ensureUnlocked(Request $request): void
    {
        abort_unless($request->session()->get('console_unlocked') === true, 401, 'The console is locked.');
        abort_if(blank(config('services.ozserver_api.console_token')), 503, 'Developer messages are not configured.');
    }

    // Sends one call to the API and passes its answer back as it came: status and JSON body.
    private function forward(callable $call): JsonResponse
    {
        $api = Http::baseUrl(rtrim((string) config('services.ozserver_api.url'), '/'))
            ->withToken((string) config('services.ozserver_api.console_token'))
            ->acceptJson()
            ->timeout(10);

        try {
            $response = $call($api);
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'Could not reach the OzServer API.'], 502);
        }

        return response()->json($response->json() ?? (object) [], $response->status() === 204 ? 200 : $response->status());
    }
}
