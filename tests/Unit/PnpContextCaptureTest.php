<?php

namespace Tests\Unit;

use App\Http\Middleware\CapturePnpContext;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class PnpContextCaptureTest extends TestCase
{
    public function test_allowed_referral_context_and_continue_url_are_captured(): void
    {
        $session = new Store('pnp-test', new ArraySessionHandler(120));
        $session->start();
        $request = Request::create('/register?src=partner&ref=key-1&campaign=fall&target=course-7&continue=https%3A%2F%2Fprosnpros.com%2Fshop');
        $request->setLaravelSession($session);

        app(CapturePnpContext::class)->handle($request, function () {
            return response('ok');
        });

        $this->assertSame([
            'acquisition_source' => 'partner',
            'external_referral_key' => 'key-1',
            'campaign_key' => 'fall',
            'target_reference' => 'course-7',
        ], $session->get('pnp_acquisition'));
        $this->assertSame('https://prosnpros.com/shop', $session->get('pnp_continue'));
    }

    public function test_blocked_continue_url_is_not_stored(): void
    {
        $session = new Store('pnp-test', new ArraySessionHandler(120));
        $session->start();
        $request = Request::create('/login?continue=https%3A%2F%2Fevil.example%2F');
        $request->setLaravelSession($session);

        app(CapturePnpContext::class)->handle($request, function () {
            return response('ok');
        });

        $this->assertFalse($session->has('pnp_continue'));
    }
}
