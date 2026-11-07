<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

test('every response carries the browser security headers, without HSTS over plain HTTP', function (string $path) {
    $response = $this->get($path);

    $policy = (string) $response->headers->get('Content-Security-Policy');
    expect($policy)->toContain("default-src 'self'")->toContain("frame-ancestors 'self'")->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")->toContain("form-action 'self'")->toContain('https://fonts.googleapis.com')
        ->not->toContain('script-src *')->not->toContain("script-src 'self' https:");
    $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'same-origin')->assertHeaderMissing('Strict-Transport-Security');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()')->toContain('geolocation=()');
})->with(['/portal', '/staff/login', '/verify-certificate/NOTAREALCODE0000', '/forgot-password']);

test('HSTS is sent only for HTTPS requests in production', function () {
    $this->get('https://localhost/portal')->assertHeaderMissing('Strict-Transport-Security');

    $this->app['env'] = 'production';
    $this->get('https://localhost/portal')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    $this->get('http://localhost/portal')->assertHeaderMissing('Strict-Transport-Security');
});

test('forwarded headers from an untrusted client are ignored, and believed only from a configured proxy', function () {
    Route::get('/__forwarded-check', fn (Request $request) => ['ip' => $request->ip(), 'secure' => $request->isSecure(), 'host' => $request->getHost()]);
    $forged = ['X-Forwarded-For' => '198.51.100.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Port' => '443'];

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeaders($forged)->getJson('http://localhost/__forwarded-check')
        ->assertExactJson(['ip' => '203.0.113.7', 'secure' => false, 'host' => 'localhost']);

    config(['trustedproxy.proxies' => '203.0.113.7']);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeaders($forged)->getJson('http://localhost/__forwarded-check')
        ->assertExactJson(['ip' => '198.51.100.9', 'secure' => true, 'host' => 'localhost']);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])->withHeaders($forged)->getJson('http://localhost/__forwarded-check')
        ->assertExactJson(['ip' => '192.0.2.50', 'secure' => false, 'host' => 'localhost']);
});

test('a forged client address does not escape the login rate limit', function () {
    $user = User::factory()->create(['role' => 'staff']);

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeaders(['X-Forwarded-For' => "198.51.100.{$attempt}"])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
    }
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->withHeaders(['X-Forwarded-For' => '198.51.100.99'])
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])->assertStatus(429);
});
