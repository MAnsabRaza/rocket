<?php

namespace Tests\Unit;

use App\Services\SafeRedirect;
use Tests\TestCase;

class SafeRedirectTest extends TestCase
{
    public function test_prosnpros_https_destinations_are_allowed(): void
    {
        $this->assertSame(
            'https://prosnpros.com/product/course?campaign=spring',
            app(SafeRedirect::class)->validate('https://prosnpros.com/product/course?campaign=spring')
        );
    }

    public function test_www_prosnpros_https_destinations_are_allowed(): void
    {
        $this->assertSame(
            'https://www.prosnpros.com/shop/item',
            app(SafeRedirect::class)->validate('https://www.prosnpros.com/shop/item')
        );
    }

    public function test_external_https_domain_is_rejected(): void
    {
        $this->assertNull(app(SafeRedirect::class)->validate('https://example.com/landing'));
    }

    public function test_http_destination_is_rejected(): void
    {
        $this->assertNull(app(SafeRedirect::class)->validate('http://prosnpros.com/landing'));
    }

    public function test_lookalike_domain_is_rejected(): void
    {
        $this->assertNull(app(SafeRedirect::class)->validate('https://prosnpros.com.evil.example/landing'));
    }

    public function test_invalid_destination_falls_back_to_panel(): void
    {
        $this->assertSame('/panel', app(SafeRedirect::class)->resolve('https://example.com/landing'));
    }

    public function test_redirect_with_credentials_is_rejected(): void
    {
        $this->assertNull(app(SafeRedirect::class)->validate('https://user:pass@prosnpros.com/panel'));
    }

    public function test_protocol_relative_and_backslash_redirects_are_rejected(): void
    {
        $redirect = app(SafeRedirect::class);

        $this->assertNull($redirect->validate('//example.com/path'));
        $this->assertNull($redirect->validate('https://prosnpros.com\\@example.com/path'));
    }

    public function test_redirect_with_unapproved_path_is_rejected(): void
    {
        config(['pnp.redirect_allowlist' => [
            ['host' => 'prosnpros.com', 'path_prefix' => '/shop'],
        ]]);

        $this->assertNull(app(SafeRedirect::class)->validate('https://prosnpros.com/admin'));
    }

    public function test_redirect_over_configured_length_limit_is_rejected(): void
    {
        config(['pnp.continue_max_length' => 20]);

        $this->assertNull(app(SafeRedirect::class)->validate('https://prosnpros.com/a-very-long-path'));
    }

    public function test_redirect_rejects_non_https_ports(): void
    {
        $redirect = app(SafeRedirect::class);

        $this->assertSame('https://prosnpros.com:443/shop', $redirect->validate('https://prosnpros.com:443/shop'));
        $this->assertNull($redirect->validate('https://prosnpros.com:8443/shop'));
    }

    public function test_path_prefix_requires_a_segment_boundary(): void
    {
        config(['pnp.redirect_allowlist' => [
            ['host' => 'prosnpros.com', 'path_prefix' => '/shop'],
        ]]);

        $redirect = app(SafeRedirect::class);

        $this->assertSame('https://prosnpros.com/shop/course', $redirect->validate('https://prosnpros.com/shop/course'));
        $this->assertNull($redirect->validate('https://prosnpros.com/shopping/course'));
    }
}
