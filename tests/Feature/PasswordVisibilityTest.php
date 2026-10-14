<?php

use App\Models\Resident;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function assertPasswordToggleCoverage(TestResponse $response): void
{
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $inputs = $xpath->query('//input[@type="password"]');
    expect($inputs->length)->toBeGreaterThan(0);
    foreach ($inputs as $input) {
        $custom = $xpath->query('//button[@data-password-toggle="'.$input->getAttribute('id').'"]');
        if ($custom->length > 0) {
            expect($custom)->toHaveCount(1);
            expect($custom->item(0)->getAttribute('type'))->toBe('button');
            expect($custom->item(0)->getAttribute('aria-controls'))->toBe($input->getAttribute('id'));
            expect($custom->item(0)->getAttribute('aria-pressed'))->toBe('false');
        } else {
            expect($xpath->query('ancestor::*[self::div or self::span][1]//button[@aria-label="Toggle password visibility"]', $input)->length)->toBeGreaterThan(0);
        }
    }
}

test('resident login passwords have localized visibility controls', function (string $locale) {
    $response = $this->withCookie('portal_locale', $locale)->get(route('portal.login'))->assertOk();
    assertPasswordToggleCoverage($response);
    $response->assertSee('password-visibility.js')->assertSee('password-visibility.css');
})->with(['en', 'fil']);

test('resident account setup has independent password and confirmation controls', function () {
    $resident = Resident::factory()->create(['status' => 'active', 'portal_registration_hash' => hash('sha256', 'ABCD1234'), 'portal_registration_expires_at' => now()->addDay()]);
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'ABCD1234']);
    $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('portal.register'))->assertOk()->assertViewHas('verified', true);
    assertPasswordToggleCoverage($response);
    $response->assertSee('data-password-toggle="registration-password"', false)->assertSee('data-password-toggle="registration-confirmation"', false);
});

test('staff auth password fields keep their built in visibility controls', function (string $route, array $parameters) {
    if ($route === 'password.confirm') {
        $this->actingAs(User::factory()->assignedOperations()->create());
    }
    assertPasswordToggleCoverage($this->get(route($route, $parameters))->assertOk());
})->with([
    ['login', []], ['password.reset', ['token' => 'test-reset-token']], ['password.confirm', []],
]);

test('admin user management and confirmation popup both have password controls', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.dashboard'))->assertOk();
    assertPasswordToggleCoverage($response);
    $response->assertSee('data-password-toggle="adduser-password"', false)->assertSee('data-password-toggle="personnel-confirm-password"', false);
});

test('staff security fields and confirmation popup all have password controls', function () {
    $response = $this->actingAs(User::factory()->assignedOperations()->create())->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertOk();
    assertPasswordToggleCoverage($response);
    $response->assertSee('password-visibility.js');
});
