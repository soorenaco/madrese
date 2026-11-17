<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaSetupTest extends TestCase
{
    public function test_ping_page_renders_the_inertia_ping_component(): void
    {
        // فایل manifest ساخته‌شده‌ی Vite در محیط تست لازم نیست
        $this->withoutVite();

        $this->get('/inertia-ping')
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ping')
                ->where('status', 'ok')
            );
    }

    public function test_ping_returns_json_for_inertia_requests(): void
    {
        $this->withoutVite();

        $version = app(HandleInertiaRequests::class)->version(request());

        $this->get('/inertia-ping', [
            'X-Inertia'         => 'true',
            'X-Inertia-Version' => (string) $version,
        ])
            ->assertOk()
            ->assertJson([
                'component' => 'Ping',
                'props'     => ['status' => 'ok'],
            ]);
    }
}
