<?php

use App\Models\DocumentRequest;
use App\Models\User;
use App\PortalLocalization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * Route throttles run before EnsureResidentAccount attaches the resident, so a plain `throttle:` limit counted per
 * network address: residents on one Wi-Fi or mobile network used up each other's quota. These tests sign in through
 * the portal form, as residents do (actingAs() would also make the resident guard the default and hide the problem).
 */

function signInResident(User $resident, string $address = '203.0.113.10'): void
{
    test()->withServerVariables(['REMOTE_ADDR' => $address])
        ->post(route('portal.login.store'), ['email' => $resident->email, 'password' => 'password'])->assertRedirect();
}

function submitRequest(string $address = '203.0.113.10'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $address])
        ->postJson(route('portal.request.store'), ['document_type' => 'Barangay Clearance', 'purpose' => 'Employment requirement']);
}

test('residents sharing one network address each keep their own document-request quota', function () {
    [$first, $second] = User::factory()->resident()->count(2)->create();

    signInResident($first);
    foreach (range(1, 10) as $attempt) {
        submitRequest()->assertOk();
    }
    submitRequest()->assertTooManyRequests()
        ->assertJsonPath('message', fn (string $message): bool => preg_match('/^You have sent several requests in a short time\. Please wait \d+ seconds, then submit again\. Nothing was lost: your request was not sent yet\.$/', $message) === 1);

    $this->post(route('portal.logout'));
    signInResident($second);
    submitRequest()->assertOk();

    expect(DocumentRequest::query()->where('resident_id', $first->resident_id)->count())->toBe(10)
        ->and(DocumentRequest::query()->where('resident_id', $second->resident_id)->count())->toBe(1);
});

test('a resident cannot reset the quota by changing device or network, and it recovers after the minute', function () {
    $resident = User::factory()->resident()->create();

    signInResident($resident, '203.0.113.10');
    foreach (range(1, 10) as $attempt) {
        submitRequest('203.0.113.10')->assertOk();
    }
    $this->post(route('portal.logout'));
    signInResident($resident, '198.51.100.20');
    submitRequest('198.51.100.20')->assertTooManyRequests();

    $this->travel(61)->seconds();
    submitRequest('198.51.100.20')->assertOk();
    expect(DocumentRequest::query()->where('resident_id', $resident->resident_id)->count())->toBe(11);
});

test('the limit message follows the resident portal language', function () {
    $resident = User::factory()->resident()->create();
    // The portal sends its request with the browser's cookies, so the JSON test request must carry them too.
    $this->withCookie(PortalLocalization::COOKIE, 'fil')->withCredentials();
    signInResident($resident);
    foreach (range(1, 10) as $attempt) {
        submitRequest()->assertOk();
    }

    submitRequest()->assertTooManyRequests()
        ->assertJsonPath('message', fn (string $message): bool => preg_match('/^Marami na kayong naipadalang request sa maikling oras\. Maghintay ng \d+ segundo, saka ipadala ulit\. Walang nawala: hindi pa naipapadala ang request ninyo\.$/', $message) === 1);
});

test('request-status polling is also counted per resident account', function () {
    [$first, $second] = User::factory()->resident()->count(2)->create();
    $statuses = route('portal.account.statuses', ['ids' => [1]]);

    signInResident($first);
    foreach (range(1, 30) as $poll) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->getJson($statuses)->assertOk();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->getJson($statuses)->assertTooManyRequests();

    $this->post(route('portal.logout'));
    signInResident($second);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->getJson($statuses)->assertOk();
});

test('both resident limits count per account with a looser cap per network address, and refuse guests', function () {
    // On a live server the throttle runs before the resident is attached; the named limiters read the resident guard.
    expect(Route::getRoutes()->getByName('portal.request.store')->gatherMiddleware())->toContain('throttle:resident-document-requests')
        ->and(Route::getRoutes()->getByName('portal.account.statuses')->gatherMiddleware())->toContain('throttle:resident-request-status');

    $resident = User::factory()->resident()->create();
    Auth::guard('resident')->setUser($resident);
    $request = Request::create('/portal/request', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);
    $limits = collect(RateLimiter::limiter('resident-document-requests')($request))->mapWithKeys(fn ($limit) => [$limit->key => $limit->maxAttempts]);
    expect($limits->all())->toBe(['resident-document-requests:account:'.$resident->id => 10, 'resident-document-requests:address:203.0.113.10' => 60]);
    $statusLimits = collect(RateLimiter::limiter('resident-request-status')($request))->pluck('maxAttempts')->all();
    expect($statusLimits)->toBe([30, 300]);

    Auth::guard('resident')->logout();
    $this->postJson(route('portal.request.store'), ['document_type' => 'Barangay Clearance', 'purpose' => 'Employment requirement'])->assertUnauthorized();
    expect(DocumentRequest::query()->count())->toBe(0);
});
