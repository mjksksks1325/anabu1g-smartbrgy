<?php

use App\CertificateType;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentPortalActivationNotification;
use App\PortalLocalization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withCredentials();
});

test('resident locale defaults to English and ignores unsupported cookie values', function (?string $locale) {
    if ($locale !== null) {
        $this->withCookie(PortalLocalization::COOKIE, $locale);
    }
    $this->get(route('home'))->assertOk()->assertSee('<html lang="en">', false)->assertSee('>Request a document</span>', false);
})->with([null, 'es', '../../en', 'FIL']);

test('locale preference accepts only the supported languages', function (mixed $locale) {
    $this->postJson(route('portal.locale'), ['locale' => $locale])->assertUnprocessable()->assertJsonValidationErrors('locale')->assertCookieMissing(PortalLocalization::COOKIE);
})->with(['unsupported' => ['es'], 'missing' => [null], 'array' => [['fil']], 'uppercase' => ['FIL']]);

test('locale preference uses a persistent private cookie without changing registration proof', function () {
    $proof = ['resident_id' => 18, 'expires_at' => now()->addMinutes(10)->timestamp];
    $response = $this->withSession(['resident_identity' => $proof])->postJson(route('portal.locale'), ['locale' => 'fil']);
    $response->assertOk()->assertJson(['locale' => 'fil'])->assertCookie(PortalLocalization::COOKIE, 'fil')->assertSessionHas('resident_identity', $proof);
    $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === PortalLocalization::COOKIE);
    expect($cookie->isHttpOnly())->toBeTrue()->and($cookie->getSameSite())->toBe('lax')->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addMonths(11)->timestamp);
    $this->withCookie(PortalLocalization::COOKIE, 'fil')->get(route('portal.login', ['next' => 'request', 'service' => 'CR']))
        ->assertOk()->assertSee('<html lang="fil">', false)->assertSee('Mag-log in')->assertSessionHas('portal_service', 'CR');
});

test('public resident pages render translated copy and the shared toggle', function (string $locale, string $label) {
    $this->withCookie(PortalLocalization::COOKIE, $locale);
    foreach (['home', 'portal.information', 'portal.officials', 'portal.login', 'portal.register', 'portal.registration.denied'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('<html lang="'.$locale.'">', false)
            ->assertSee('data-portal-language-form', false)->assertSee('English')->assertSee('Filipino')->assertSee('>'.$label.'</span>', false);
    }
})->with(['English' => ['en', 'Request a document'], 'Filipino' => ['fil', 'Mag-request ng dokumento']]);

test('validation and account errors use the resident language', function (string $locale, string $required, string $credentials) {
    $this->withCookie(PortalLocalization::COOKIE, $locale);
    $this->postJson(route('portal.login.store'), [])->assertUnprocessable()->assertJsonPath('errors.email.0', $required);
    $this->postJson(route('portal.login.store'), ['email' => 'missing@example.test', 'password' => 'incorrect'])
        ->assertUnprocessable()->assertJsonPath('errors.email.0', $credentials);
})->with([
    'English' => ['en', 'The email field is required.', 'The provided resident account credentials are incorrect or the account cannot currently use online services.'],
    'Filipino' => ['fil', 'Kailangan ang email.', 'Hindi tama ang inilagay na detalye ng resident account o hindi magamit ng account ang online services sa ngayon.'],
]);

test('resident header uses concise localized menu labels while keeping descriptive accessible links', function (string $locale, string $request, string $requirements, string $help, string $description) {
    $this->withCookie(PortalLocalization::COOKIE, $locale);
    foreach (['home', 'portal.login', 'portal.register', 'portal.information'] as $route) {
        $html = new DOMDocument;
        @$html->loadHTML('<?xml encoding="utf-8" ?>'.$this->get(route($route))->assertOk()->getContent());
        $xpath = new DOMXPath($html);
        foreach (['request' => $request, 'requirements' => $requirements, 'help' => $help] as $key => $label) {
            expect($xpath->evaluate('string(//nav[@id="site-nav"]//span[@data-portal-i18n="portal.navigation.'.$key.'"])'))->toBe($label);
            expect(PortalLocalization::catalogs()[$locale]['portal.navigation.'.$key])->toBe($label);
        }
        expect($xpath->evaluate('string(//nav[@id="site-nav"]//a[@data-portal-i18n-aria-label="Request a document"]/@aria-label)'))->toBe($description);
        expect($xpath->evaluate('string(//nav[@id="site-nav"]//a[@data-portal-i18n-aria-label="Request a document"]/@href)'))->toBe(route('portal.request.create'));
    }
})->with([
    'English' => ['en', 'Request a document', 'Requirements and fees', 'Get help', 'Request a document'],
    'Filipino' => ['fil', 'Mag-request', 'Kailangan at bayarin', 'Tulong', 'Mag-request ng dokumento'],
]);

test('activation account setup login requests profile and logout retain language and stored identifiers', function (string $locale) {
    Storage::fake('local');
    $this->withCookie(PortalLocalization::COOKIE, $locale);
    $resident = Resident::factory()->create(['portal_registration_hash' => hash('sha256', 'ABCD1234'), 'portal_registration_expires_at' => now()->addDay()]);
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'abcd1234'])->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertOk()->assertSee($locale === 'fil' ? 'Hakbang 4 sa 4' : 'Step 4 of 4')->assertSee('name="photo"', false);
    $input = ['email' => 'language@example.test', 'password' => 'ResidentPassword123!', 'password_confirmation' => 'ResidentPassword123!', 'photo' => UploadedFile::fake()->image('resident.jpg')];
    $this->post(route('portal.register.store'), $input)->assertRedirect(route('portal.login'));
    $this->post(route('portal.login.store'), ['email' => $input['email'], 'password' => $input['password']])->assertRedirect(route('portal.account'));
    $this->get(route('portal.request.create'))->assertOk()->assertSee($locale === 'fil' ? 'Katibayan ng Paninirahan' : 'Certificate of Residency');
    $this->postJson(route('portal.request.store'), ['document_type' => CertificateType::CertificateOfResidency->value, 'purpose' => 'Employment'])
        ->assertOk()->assertJsonPath('message', $locale === 'fil' ? 'Matagumpay na na-submit ang request ng dokumento.' : 'Document request submitted successfully.');
    $request = $resident->documentRequests()->sole();
    expect($request->document_type)->toBe('Certificate of Residency')->and($request->purpose)->toBe('Employment');
    $this->get(route('portal.account'))->assertOk()->assertSee($request->reference_code)->assertSee($resident->full_name)->assertSee($locale === 'fil' ? 'Natanggap' : 'Received');
    $this->get(route('portal.profile'))->assertOk()->assertSee($resident->full_name)->assertSee($locale === 'fil' ? 'Larawan ng residente' : 'Resident photo');
    $this->post(route('portal.profile.photo.store'), ['photo' => UploadedFile::fake()->image('new.png')])->assertRedirect(route('portal.profile'));
    $this->post(route('portal.logout'))->assertRedirect(route('home'));
    $this->get(route('home'))->assertOk()->assertSee('<html lang="'.$locale.'">', false);
})->with(['en', 'fil']);

test('resident language does not change personnel locale or authorize private resident data', function () {
    $this->withCookie(PortalLocalization::COOKIE, 'fil')->get(route('portal.profile'))->assertRedirect();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin, 'web')->get(route('admin.users.index'))->assertOk();
    expect(app()->getLocale())->toBe('en');
    $this->getJson(route('portal.identity'))->assertUnauthorized()->assertJsonPath('message', 'Mag-log in sa inyong resident account para magpatuloy.');
});

test('resident throttling and authenticated endpoints return translated errors', function () {
    $this->withCookie(PortalLocalization::COOKIE, 'fil');
    $this->getJson(route('portal.request.status', 'REQ-PRIVATE'))->assertUnauthorized()
        ->assertJsonPath('message', 'Mag-log in sa inyong resident account para magpatuloy.');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson(route('portal.login.store'), ['email' => 'throttled@example.test', 'password' => 'incorrect'])->assertUnprocessable();
    }
    $this->postJson(route('portal.login.store'), ['email' => 'throttled@example.test', 'password' => 'incorrect'])
        ->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('message', 'Masyadong maraming pagsubok. Maghintay muna bago subukan ulit.');
});

test('translation assets contain interface copy but no private resident information', function () {
    $user = User::factory()->resident()->create(['email' => 'private-locale@example.test']);
    $this->actingAs($user, 'resident')->get(route('portal.translations'))->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
        ->assertSee('window.PORTAL_I18N.catalogs', false)->assertSee('Kailangan ang :attribute.')
        ->assertDontSee($user->email)->assertDontSee($user->resident->resident_number);
});

test('existing activation notification uses its selected locale without translating activation data', function () {
    $notification = (new ResidentPortalActivationNotification('ANB-UNCHANGED', 'ABCD1234'))->locale('fil');
    app()->setLocale('fil');
    $mail = $notification->toMail(new stdClass);
    expect($mail->subject)->toBe('Pag-activate ng Resident Portal ng Barangay Anabu I-G');
    $this->assertStringContainsString('ABCD1234', view('mail.resident-activation-text', $mail->viewData)->render());
    $this->assertStringContainsString('ANB-UNCHANGED', view('mail.resident-activation-text', $mail->viewData)->render());
    $this->assertStringContainsString('Ipagpatuloy ang pagpaparehistro', view('mail.resident-activation-text', $mail->viewData)->render());
});

test('self service activation mail captures the resident locale', function () {
    Notification::fake();
    config(['mail.default' => 'smtp']);
    $resident = Resident::factory()->create();
    $this->withCookie(PortalLocalization::COOKIE, 'fil')->withSession(['resident_identity' => ['resident_id' => $resident->id, 'expires_at' => now()->addMinutes(10)->timestamp]])
        ->post(route('portal.register.send-code'), ['email' => 'locale@example.test'])->assertRedirect(route('portal.register'));
    Notification::assertSentOnDemand(ResidentPortalActivationNotification::class, fn ($notification): bool => $notification->locale === 'fil');
});
