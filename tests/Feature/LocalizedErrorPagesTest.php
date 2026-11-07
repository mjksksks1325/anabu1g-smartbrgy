<?php

use App\Http\Responses\PasswordResetLinkRequestResponse;
use App\Models\Resident;
use App\Models\User;
use App\PortalLocalization;

/*
 * Pages answered before, or outside, the portal's own addresses still follow the language the visitor chose: the
 * "file too large" page (PHP refuses the form before the web middleware runs) and the resident password reset pages
 * (Fortify's /forgot-password and /reset-password, marked from=portal).
 */

test('a form larger than PHP accepts is explained in the visitor\'s language, without server details', function (string $locale, string $heading, string $back) {
    $response = $this->withCookie(PortalLocalization::COOKIE, $locale)
        ->withServerVariables(['CONTENT_LENGTH' => (string) (64 * 1024 * 1024), 'HTTP_REFERER' => route('portal.new-resident.create')])
        ->post(route('portal.new-resident.store'));

    $response->assertStatus(413)
        ->assertSee('<html lang="'.$locale.'">', false)
        ->assertSee($heading)
        ->assertSee($back)
        ->assertSee('href="'.route('portal.new-resident.create').'"', false)
        ->assertDontSee('post_max_size')->assertDontSee('PostTooLargeException')->assertDontSee(base_path());
})->with([
    'English' => ['en', 'The file you sent is too large.', 'Go back to the form'],
    'Filipino' => ['fil', 'Masyadong malaki ang file na ipinadala.', 'Bumalik sa form'],
]);

test('a background upload larger than PHP accepts gets a JSON answer in the staff language', function () {
    $resident = Resident::factory()->create();

    $this->actingAs(User::factory()->assignedOperations()->create())->withCookie(PortalLocalization::ADMIN_COOKIE, 'fil')
        ->withServerVariables(['CONTENT_LENGTH' => (string) (64 * 1024 * 1024)])->withHeaders(['Accept' => 'application/json'])
        ->post(route('staff.residents.photo.store', $resident))
        ->assertStatus(413)
        ->assertExactJson(['message' => 'Masyadong malaki ang file na ipinadala kaya walang na-save. Pumili ng mas maliit na file (hanggang 10 MB ang larawan ng residente) at subukan ulit.']);
});

test('the resident password reset pages and their answer follow the portal language', function () {
    $this->withCookie(PortalLocalization::COOKIE, 'fil');

    $this->get(route('password.request', ['from' => 'portal']))->assertOk()->assertSee('<html lang="fil">', false)
        ->assertSee('name="from" value="portal"', false);
    $this->from(route('password.request', ['from' => 'portal']))->post(route('password.email'), ['email' => 'nobody@example.test', 'from' => 'portal'])
        ->assertSessionHas('status', __(PasswordResetLinkRequestResponse::MESSAGE, locale: 'fil'));
    expect(__(PasswordResetLinkRequestResponse::MESSAGE, locale: 'fil'))->not->toBe(PasswordResetLinkRequestResponse::MESSAGE);

    $this->get(route('password.reset', ['token' => 'token', 'email' => 'nobody@example.test', 'from' => 'portal']))->assertOk()
        ->assertSee('<html lang="fil">', false)->assertSee('name="from" value="portal"', false);
});

test('staff password pages stay in English, and a cookie with an unknown language is ignored', function () {
    $this->withCookie(PortalLocalization::COOKIE, 'fil')->get(route('password.request'))->assertOk()->assertSee('<html lang="en"', false);
    $this->withCookie(PortalLocalization::COOKIE, 'es')
        ->withServerVariables(['CONTENT_LENGTH' => (string) (64 * 1024 * 1024)])->post(route('portal.new-resident.store'))
        ->assertStatus(413)->assertSee('<html lang="en">', false);
});
