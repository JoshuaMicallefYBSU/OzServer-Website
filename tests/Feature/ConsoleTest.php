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
}
