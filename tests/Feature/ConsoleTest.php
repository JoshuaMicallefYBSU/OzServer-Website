<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ozserver_api.url' => 'https://api.example.test',
            'services.ozserver_api.console_password' => 'correct horse',
            'services.ozserver_api.console_token' => 'console-token-0123456789abcdef',
        ]);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_the_console_does_not_exist_without_a_password(): void
    {
        config(['services.ozserver_api.console_password' => null]);

        $this->get('/console')->assertNotFound();
    }

    public function test_the_password_is_not_in_the_page(): void
    {
        $this->get('/console')->assertOk()->assertDontSee('correct horse');
    }

    public function test_unlocks_only_with_the_right_password(): void
    {
        $this->postJson('/console/unlock', ['password' => 'wrong'])->assertStatus(401);
        $this->getJson('/console/api/messages')->assertStatus(401);

        $this->postJson('/console/unlock', ['password' => 'correct horse'])->assertOk();
        $this->assertTrue(session('console_unlocked'));
    }

    public function test_sending_forwards_to_the_api_with_the_token(): void
    {
        Http::fake(['api.example.test/*' => Http::response(['id' => 7], 201)]);

        $this->withSession(['console_unlocked' => true])
            ->postJson('/console/api/messages', [
                'body' => 'Please restart vatSys',
                'server' => 'live',
                'callsigns' => [],
                // Whatever the browser says, every message is signed by the team.
                'sent_by' => 'Someone Else',
            ])
            ->assertStatus(201)
            ->assertJson(['id' => 7]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.example.test/api/v1/admin/messages'
            && $request->hasHeader('Authorization', 'Bearer console-token-0123456789abcdef')
            && $request['title'] === ''
            && $request['callsigns'] === null
            && $request['allow_reply'] === true
            && $request['expires_minutes'] === 15
            && $request['sent_by'] === 'OzServer Development Team');
    }

    public function test_sending_to_chosen_controllers_passes_the_list(): void
    {
        Http::fake(['api.example.test/*' => Http::response(['id' => 8], 201)]);

        $this->withSession(['console_unlocked' => true])
            ->postJson('/console/api/messages', ['body' => 'Hi', 'callsigns' => ['ML-BLA_CTR', 'ML_APP']])
            ->assertStatus(201);

        Http::assertSent(fn (Request $request) => $request['callsigns'] === ['ML-BLA_CTR', 'ML_APP']);
    }

    public function test_sending_validates_before_forwarding(): void
    {
        Http::fake();

        $this->withSession(['console_unlocked' => true])
            ->postJson('/console/api/messages', ['title' => '', 'body' => 'x', 'expires_minutes' => 1, 'sent_by' => 'Dev'])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_a_password_that_is_not_a_string_is_a_wrong_password(): void
    {
        $this->postJson('/console/unlock', ['password' => ['correct horse']])->assertStatus(401);
        $this->assertNull(session('console_unlocked'));
    }

    public function test_a_withdraw_id_too_long_for_an_integer_is_not_a_route(): void
    {
        Http::fake();

        $this->withSession(['console_unlocked' => true])
            ->postJson('/console/api/messages/99999999999999999999/withdraw')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_the_api_refusing_the_token_does_not_look_like_a_locked_console(): void
    {
        Http::fake(['api.example.test/*' => Http::response(['message' => 'A valid console token is required.'], 401)]);

        $this->withSession(['console_unlocked' => true])
            ->getJson('/console/api/clients?server=all')
            ->assertStatus(502);
    }

    public function test_the_log_feed_is_behind_the_console_password(): void
    {
        Http::fake();

        $this->getJson('/console/api/logs?since=2+minutes&limit=1000')->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_the_log_feed_forwards_to_the_api_without_a_token_when_none_is_set(): void
    {
        // Messages are not configured here: the feed does not depend on them.
        config(['services.ozserver_api.console_token' => null]);
        Http::fake(['api.example.test/*' => Http::response([['message' => 'Claimed BLA', 'context' => (object) []]])]);

        $response = $this->withSession(['console_unlocked' => true])
            ->getJson('/console/api/logs?since=1+hour&limit=5000&server=sb1')
            ->assertOk()
            ->assertJsonPath('0.message', 'Claimed BLA');
        // Passed back as it came: an empty object is still an object.
        $this->assertStringContainsString('"context":{}', $response->getContent());

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.example.test/api/v1/client-logs?')
            && $request['since'] === '1 hour'
            && (int) $request['limit'] === 5000
            && $request['server'] === 'sb1'
            && ! $request->hasHeader('Authorization'));
    }

    public function test_the_log_feed_sends_its_own_token_when_one_is_set(): void
    {
        config(['services.ozserver_api.client_logs_token' => 'logs-token-0123456789']);
        Http::fake(['api.example.test/*' => Http::response([])]);

        $this->withSession(['console_unlocked' => true])
            ->getJson('/console/api/logs?since=2+minutes&limit=1000')
            ->assertOk();

        // Every server: no server is passed on. And never the messages token.
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer logs-token-0123456789')
            && ! str_contains($request->url(), 'server='));
    }

    public function test_the_log_feed_validates_before_forwarding(): void
    {
        Http::fake();

        $session = $this->withSession(['console_unlocked' => true]);
        $session->getJson('/console/api/logs?since=100+years&limit=1000')->assertStatus(422);
        $session->getJson('/console/api/logs?since=2+minutes&limit=50000')->assertStatus(422);
        $session->getJson('/console/api/logs?since=2+minutes&limit=1000&server=prod')->assertStatus(422);

        Http::assertNothingSent();
    }
}
