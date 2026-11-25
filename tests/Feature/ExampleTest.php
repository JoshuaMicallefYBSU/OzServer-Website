<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // The homepage reads sector shapes from the API: answered here, never by the real one.
        Http::fake(['*/api/v1/map/sector-shapes' => Http::response([])]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_homepage_draws_only_australian_sectors(): void
    {
        $triangle = fn (float $lon, float $lat) => [[
            ['lat' => $lat, 'lon' => $lon], ['lat' => $lat - 1, 'lon' => $lon], ['lat' => $lat, 'lon' => $lon + 1],
        ]];
        Http::fake(['*/api/v1/map/sector-shapes' => Http::response([
            ['name' => 'BLA', 'type' => 'CTR', 'region' => 'australia', 'responsible_sectors' => [], 'boundary' => $triangle(110.0, -6.0)],
            ['name' => 'MEX1', 'type' => 'CTR', 'region' => 'mexico', 'responsible_sectors' => [], 'boundary' => $triangle(-100.0, 20.0)],
        ])]);

        $response = $this->get('/');

        // BLA's first corner is the map's own top left; MEX1 would sit far off to the left of it.
        $response->assertOk()->assertSee('M0 0 L', false)->assertDontSee('M-442', false);
        Http::assertSentCount(1);
    }

    public function test_the_how_to_guide_shows_the_screenshots_it_has(): void
    {
        // No document root in a test, as when public/ is the web root: the path has no prefix.
        $this->get('/how-to')->assertOk()
            ->assertSee('src="/images/how-to/arrival-list.png?v=', false)
            ->assertDontSee('pubic', false);
    }

    public function test_the_how_to_guide_finds_public_under_a_project_web_root(): void
    {
        // As ozserver.org is hosted: the web root is the project folder, so public/ is in the URL.
        $this->withServerVariables(['DOCUMENT_ROOT' => base_path()])->get('/how-to')->assertOk()
            ->assertSee('src="/public/images/how-to/arrival-list.png?v=', false);
    }
}
