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

        // BLA's first corner in the chart's projection; MEX1 would sit far off to the left of it.
        $response->assertOk()->assertSee('<path class="sector" d="M467.4 60L', false)->assertDontSee('M-33', false);
        Http::assertSentCount(1);
    }

    public function test_the_homepage_draws_the_coastline_and_names_no_controller_itself(): void
    {
        Http::fake(['*/api/v1/map/sector-shapes' => Http::response([])]);

        // Who holds what is read by the page from the live feed, never baked in by the server.
        $this->get('/')->assertOk()
            ->assertSee('<path class="land" d="M', false)
            ->assertSee('<g id="held"></g>', false)
            ->assertSee('/api/v1/map/sectors?server=live', false);
    }

    public function test_the_homepage_shows_the_plugin_windows_it_has_pictures_of(): void
    {
        Http::fake(['*/api/v1/map/sector-shapes' => Http::response([])]);

        $this->get('/')->assertOk()
            ->assertSee('src="/images/how-to/sectors-window.png?v=', false)
            ->assertSee('Answer a request');
    }

    public function test_every_window_picture_is_on_the_page_from_the_start(): void
    {
        Http::fake(['*/api/v1/map/sector-shapes' => Http::response([])]);

        // Picking a window must fetch nothing: each one is an <img> in the page, loaded eagerly.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(6, substr_count($html, 'role="tabpanel"'));
        $this->assertSame(6, substr_count($html, 'loading="eager"'));
        foreach (['sectors-window', 'request-prompt', 'incoming-request', 'take-sectors-disconnected', 'arrival-list', 'update-window'] as $picture) {
            $this->assertStringContainsString('src="/images/how-to/'.$picture.'.png?v=', $html);
        }
    }

    public function test_pages_are_gzipped_for_a_browser_that_accepts_it(): void
    {
        Http::fake(['*/api/v1/map/sector-shapes' => Http::response([])]);

        $plain = $this->get('/');
        $plain->assertOk()->assertHeaderMissing('Content-Encoding');

        $packed = $this->withHeaders(['Accept-Encoding' => 'gzip, deflate, br'])->get('/');
        $packed->assertOk()->assertHeader('Content-Encoding', 'gzip');

        $this->assertSame($plain->getContent(), gzdecode($packed->getContent()));
        $this->assertStringContainsString('Accept-Encoding', (string) $packed->headers->get('Vary'));
    }

    public function test_every_page_links_the_site_icon_where_public_is_served(): void
    {
        Http::fake(['*/api/v1/map/sector-shapes' => Http::response([])]);

        $pages = ['/', '/how-to', '/live-map'];

        foreach ($pages as $page) {
            $this->get($page)->assertOk()->assertSee('href="/favicon.svg?v=', false);
        }

        // As ozserver.org is hosted. Set once: server variables stay for the rest of the test.
        $this->withServerVariables(['DOCUMENT_ROOT' => base_path()]);

        foreach ($pages as $page) {
            $this->get($page)->assertOk()->assertSee('href="/public/favicon.svg?v=', false);
        }
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
