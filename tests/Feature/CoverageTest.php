<?php

namespace Tests\Feature;

use App\Console\Kernel as AppConsoleKernel;
use App\Events\NewEvent;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrustHosts;
use App\Providers\BroadcastServiceProvider;
use App\Providers\RouteServiceProvider;
use Illuminate\Broadcasting\Channel;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class CoverageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_event_broadcasts_on_channel(): void
    {
        $event = new NewEvent('hello');
        $channels = $event->broadcastOn();

        $this->assertSame('hello', $event->message);
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('new-public-channel', (string) $channels[0]);
    }

    public function test_authenticate_redirects_to_login_when_not_json(): void
    {
        Route::get('/login', fn () => 'login')->name('login');
        Route::getRoutes()->refreshNameLookups();

        $middleware = new TestableAuthenticate(app('auth'));
        $request = Request::create('/', 'GET');

        $this->assertSame(url('/login'), $middleware->callRedirectTo($request));
    }

    public function test_authenticate_returns_null_for_json_request(): void
    {
        $middleware = new TestableAuthenticate(app('auth'));
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertNull($middleware->callRedirectTo($request));
    }

    public function test_redirect_if_authenticated_redirects_when_guard_authenticates(): void
    {
        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('guard')->once()->with(null)->andReturn($guard);

        $middleware = new RedirectIfAuthenticated;
        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('next'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(url(RouteServiceProvider::HOME), $response->headers->get('Location'));
    }

    public function test_redirect_if_authenticated_passes_through_when_not_authenticated(): void
    {
        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->once()->with(null)->andReturn($guard);

        $middleware = new RedirectIfAuthenticated;
        $nextCalled = false;

        $response = $middleware->handle(Request::create('/', 'GET'), function () use (&$nextCalled) {
            $nextCalled = true;

            return response('next');
        });

        $this->assertTrue($nextCalled);
        $this->assertSame('next', $response->getContent());
    }

    public function test_trust_hosts_returns_hosts_array(): void
    {
        $middleware = new TrustHosts(app());
        $hosts = $middleware->hosts();

        $this->assertIsArray($hosts);
        $this->assertNotEmpty($hosts);
        $this->assertIsString($hosts[0]);
    }

    public function test_broadcast_service_provider_registers_routes(): void
    {
        (new BroadcastServiceProvider(app()))->boot();

        $found = false;

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === 'broadcasting/auth') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    public function test_route_service_provider_registers_rate_limiter(): void
    {
        (new RouteServiceProvider(app()))->boot();

        $this->assertNotNull(RateLimiter::limiter('api'));
    }

    public function test_console_kernel_schedule_and_commands_are_callable(): void
    {
        $kernel = app(TestableConsoleKernel::class);
        $kernel->callSchedule(app(Schedule::class));
        $kernel->callCommands();

        $this->assertTrue(true);
    }
}

class TestableAuthenticate extends Authenticate
{
    public function callRedirectTo(Request $request): ?string
    {
        return $this->redirectTo($request);
    }
}

class TestableConsoleKernel extends AppConsoleKernel
{
    public function callSchedule(Schedule $schedule): void
    {
        $this->schedule($schedule);
    }

    public function callCommands(): void
    {
        $this->commands();
    }
}
