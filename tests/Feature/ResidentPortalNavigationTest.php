<?php

use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

test('portal home shows community content without the document request form', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertViewIs('portal.index')
        ->assertSee('images/barangay-hall-anabu-1g.jpg')
        ->assertSee('Mabilis. Malinaw. Maaasahang serbisyo.')
        ->assertSee('Check status')
        ->assertSee('Secure resident portal')
        ->assertSee('GOVPH')
        ->assertSee('css/figma-portal.css')
        ->assertSee('<body class="has-hero-header">', false)
        ->assertSee('data-resident-theme aria-pressed="false" aria-label="Dark mode"', false)
        ->assertSee('theme-icon-moon', false)
        ->assertSee('theme-icon-sun', false)
        ->assertDontSee('class="dark-mode', false)
        ->assertDontSee('Staff portal')
        ->assertSee(route('portal.request.create'), false)
        ->assertSee('Request a document')
        ->assertDontSee('id="request-flow"', false)
        ->assertDontSee('id="screen-terms"', false)
        ->assertDontSee('id="resident-details-form"', false);
});

test('officials page is linked from home and the footer for guests and residents and marks its current page', function () {
    $officialsLink = 'href="'.route('portal.officials').'"';

    $this->get(route('home'))->assertSee($officialsLink, false);
    $response = $this->get(route('portal.officials'));
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//footer//a[@href="'.route('portal.officials').'"][@aria-current="page"]'))->toHaveCount(1);
    expect($xpath->query('//nav[@id="site-nav"]//a[@href="'.route('portal.officials').'"]'))->toHaveCount(0);

    $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->get(route('portal.account'))
        ->assertSee($officialsLink, false);
});

test('guest hero status opens resident sign in and signed in status opens request history', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('portal.login').'"><span data-portal-i18n="Check status">Check status', false);

    $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->get(route('home'))
        ->assertSee('href="'.route('portal.account').'"><span data-portal-i18n="Check status">Check status', false);
});

test('public portal shows community information and usable service links', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="population"', false)
        ->assertSee('2,345')
        ->assertSee('2024 Census of Population')
        ->assertSee('id="announcements"', false)
        ->assertSee(route('portal.announcements.index'), false)
        ->assertSee('id="contacts"', false)
        ->assertSee('tel:+63468889911', false)
        ->assertSee('tel:+639985985601', false)
        ->assertSee('tel:+639155283256', false)
        ->assertSee('id="location"', false)
        ->assertSee('id="quick-links"', false)
        ->assertSee(route('portal.request.create'), false)
        ->assertSee(route('portal.information').'#requirements', false);
});

test('portal home lists confirmed document fees and flags the business clearance fee for confirmation', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['>Documents and fees</span>', '>Business Clearance fee: To be confirmed</span>', '>Barangay Clearance</span>', '>PHP 25.00</span>', '>Certificate of Residency</span>', '>PHP 25.00</span>', '>Certificate of Indigency</span>', '>Free</span>', '>Business Clearance</span>', '>PHP 200.00</span>'], false);
});

test('requirements page lists confirmed document fees and flags the business clearance fee for confirmation', function () {
    $this->get(route('portal.information'))
        ->assertOk()
        ->assertSeeInOrder(['>Barangay Clearance</span>', '>PHP 25.00</span>', '>Certificate of Residency</span>', '>PHP 25.00</span>', '>Certificate of Indigency</span>', '>Free</span>', '>Business Clearance</span>', '>PHP 200.00</span>', '>To be confirmed</span>'], false);
});

test('document request page renders the terms when a resident is signed in', function () {
    $resident = User::factory()->resident()->create();

    $this->actingAs($resident, 'resident')
        ->get(route('portal.request.create'))
        ->assertOk()
        ->assertViewIs('portal.request')
        ->assertSee('id="request-flow" aria-label="Document request"', false)
        ->assertSee('Terms and Conditions')
        ->assertDontSee('id="population"', false);
});

test('document request page asks residents to review before submitting', function () {
    $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->get(route('portal.request.create'))
        ->assertOk()
        ->assertSeeInOrder(['id="screen-attachment"', 'onclick="goToReview()"', 'id="screen-review"', 'id="review-summary"', 'id="submit-request-button"', 'id="screen-confirm"'], false)
        ->assertSee('Hindi pa kumpirmado ng barangay ang office hours');
});

test('navigation shows account entry points for guests and account links for residents', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.route('portal.login').'"', false)
        ->assertSee('href="'.route('portal.register').'"', false)
        ->assertDontSee('data-resident-logout', false)
        ->assertDontSee('href="'.route('portal.profile').'"', false);

    $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.route('portal.account').'"', false)
        ->assertSee('href="'.route('portal.profile').'"', false)
        ->assertSee('data-resident-logout', false)
        ->assertDontSee('href="'.route('portal.register').'"', false);
});

test('legacy request links redirect to the dedicated request page', function () {
    $resident = User::factory()->resident()->create();

    $this->actingAs($resident, 'resident')
        ->get(route('home', ['request' => 1, 'service' => 'BC']))
        ->assertRedirect(route('portal.request.create', ['service' => 'BC']));
});

test('request buttons reach resident login while an employee remains authenticated', function (string $role) {
    $employee = User::factory()->assignedOperations()->create(['role' => $role]);

    $this->actingAs($employee)->followingRedirects()->get(route('portal.request.create', ['service' => 'BC']))
        ->assertOk()->assertViewIs('portal.login');
    $this->get(route('portal.login'))->assertViewIs('portal.login');
    $this->get(route('portal.register'))->assertViewIs('portal.register');
    $this->assertAuthenticatedAs($employee);
    $this->assertGuest('resident');
})->with(['admin', 'staff', 'viewer']);

test('guest request buttons reach the separate resident login', function () {
    $this->followingRedirects()->get(route('portal.request.create', ['service' => 'BC']))
        ->assertOk()->assertViewIs('portal.login');
    $this->get(route('portal.login'))->assertViewIs('portal.login');
    $this->get(route('portal.register'))->assertViewIs('portal.register');
    $this->assertGuest();
    $this->assertGuest('resident');
});

test('resident returns to the request page after signing in from a request link', function () {
    $resident = User::factory()->resident()->create();

    $this->get(route('portal.request.create'))->assertRedirect(route('portal.login', ['next' => 'request', 'service' => '']));
    $this->get(route('portal.login', ['next' => 'request']))->assertOk();
    $this->post(route('portal.login.store'), ['email' => $resident->email, 'password' => 'password'])
        ->assertRedirect(route('portal.request.create'));
});

test('ineligible resident cannot open the document request page', function () {
    $resident = User::factory()->resident()->create();
    $resident->resident->update(['status' => 'inactive']);

    $this->actingAs($resident, 'resident')
        ->get(route('portal.request.create'))
        ->assertForbidden()
        ->assertSee('Please visit Barangay');
});

test('authenticated employees can use resident registration without ending their employee session', function () {
    Storage::fake('local');
    $employee = User::factory()->assignedOperations()->create(['role' => 'staff']);
    $resident = Resident::factory()->create([
        'portal_registration_hash' => hash('sha256', 'private-test-activation-code'),
        'portal_registration_expires_at' => now()->addDay(),
    ]);

    $this->actingAs($employee)->post(route('portal.register.verify'), [
        'resident_number' => $resident->resident_number,
        'activation_code' => 'private-test-activation-code',
    ])->assertRedirect(route('portal.register'));
    $this->post(route('portal.register.store'), [
        'email' => 'resident@example.test',
        'password' => 'ResidentPassword123!',
        'password_confirmation' => 'ResidentPassword123!',
        'photo' => UploadedFile::fake()->image('resident.jpg'),
    ])->assertRedirect(route('portal.login'));

    $this->assertAuthenticatedAs($employee);
    $this->assertGuest('resident');
    $this->assertDatabaseCount('users', 2);
});

test('an authenticated resident stays in the resident portal when opening account entry links', function () {
    $resident = User::factory()->resident()->create();

    $this->actingAs($resident, 'resident');
    $this->get(route('portal.login'))->assertRedirect(route('home'));
    $this->get(route('portal.register'))->assertRedirect(route('portal.account'));
});

test('employee and resident accounts can be authenticated at the same time', function () {
    $employee = User::factory()->superAdmin()->create();
    $resident = User::factory()->resident()->create();

    $this->actingAs($employee)->post(route('portal.login.store'), [
        'email' => $resident->email,
        'password' => 'password',
    ])->assertRedirect(route('portal.account'));

    $this->assertAuthenticatedAs($employee, 'web');
    $this->assertAuthenticatedAs($resident, 'resident');
    $this->get(route('staff.dashboard'))->assertOk();
    $this->get(route('portal.account'))->assertOk();
});

test('resident logout preserves the employee session', function () {
    $employee = User::factory()->superAdmin()->create();
    $resident = User::factory()->resident()->create();

    $this->actingAs($employee)->actingAs($resident, 'resident')
        ->post(route('portal.logout'))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($employee, 'web');
    $this->assertGuest('resident');
    Auth::shouldUse('web');
    $this->get(route('staff.dashboard'))->assertOk();
});

test('employee logout preserves the resident session', function () {
    $employee = User::factory()->superAdmin()->create();
    $resident = User::factory()->resident()->create();

    $this->actingAs($employee)->actingAs($resident, 'resident');
    Auth::shouldUse('web');
    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest('web');
    $this->assertAuthenticatedAs($resident, 'resident');
    $this->get(route('portal.account'))->assertOk();
});

test('staff login rejects resident credentials and resident login rejects staff credentials', function () {
    $employee = User::factory()->assignedOperations()->create(['role' => 'staff']);
    $resident = User::factory()->resident()->create();

    $this->post(route('login.store'), ['email' => $resident->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->post(route('portal.login.store'), ['email' => $employee->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('web');
    $this->assertGuest('resident');
});

test('simultaneous employee access does not widen resident request status access', function () {
    $employee = User::factory()->superAdmin()->create();
    $resident = User::factory()->resident()->create();
    $otherResident = Resident::factory()->create();
    $otherRequest = $otherResident->documentRequests()->create([
        'reference_code' => 'REQ-OTHER-RESIDENT',
        'source' => 'online',
        'document_type' => 'Barangay Clearance',
        'full_name' => $otherResident->full_name,
        'address' => $otherResident->address,
        'status' => 'pending',
    ]);

    $this->actingAs($employee)->actingAs($resident, 'resident')
        ->getJson(route('portal.request.status', $otherRequest->reference_code))->assertNotFound();

    Auth::shouldUse('web');
    $this->get(route('staff.document-requests.show', $otherRequest))
        ->assertOk()->assertSee('REQ-OTHER-RESIDENT');
});

it('overlays the header on the home hero photo and restores it after scrolling', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('has-hero-header', false)
        ->assertSee("classList.toggle('hero-header-scrolled'", false);

    $this->get(route('portal.information'))->assertOk()
        ->assertDontSee('has-hero-header', false);
});
test('guests and residents share the same service menu without account pages in it', function () {
    $menu = function () {
        $document = new DOMDocument;
        @$document->loadHTML($this->get(route('portal.information'))->assertOk()->getContent());

        return collect((new DOMXPath($document))->query('//nav[@id="site-nav"]//ul[@class="site-nav-list"]//a'))
            ->map(fn (DOMElement $link): string => trim($link->textContent))->all();
    };

    expect($menu())->toBe(['Home', 'Request a document', 'My requests', 'Get help']);
    $this->actingAs(User::factory()->resident()->create(), 'resident');
    expect($menu())->toBe(['Home', 'Request a document', 'My requests', 'Get help']);
});

test('the resident name button is the single entry to account pages', function () {
    $user = User::factory()->resident()->create();
    $user->resident->update(['first_name' => 'Rosa', 'last_name' => 'Dela Cruz']);

    $document = new DOMDocument;
    @$document->loadHTML($this->actingAs($user, 'resident')->get(route('portal.information'))->assertOk()->getContent());
    $xpath = new DOMXPath($document);
    $button = $xpath->query('//button[@data-account-menu-button]')->item(0);

    expect(preg_replace('/\s+/', ' ', trim($button->textContent)))->toBe('Rosa D. Account menu');
    expect($button->getAttribute('aria-expanded'))->toBe('false');
    $list = $document->getElementById($button->getAttribute('aria-controls'));
    expect($list->hasAttribute('hidden'))->toBeTrue();
    expect(collect($xpath->query('.//a', $list))->map(fn (DOMElement $link): string => $link->getAttribute('href'))->all())
        ->toBe([route('portal.account.home'), route('portal.profile'), route('password.request', ['from' => 'portal'])]);
    expect($xpath->query('.//button[@data-resident-logout]', $list))->toHaveCount(1);
    expect($xpath->query('//nav[@id="site-nav"]//a[@href="'.route('portal.account.home').'"]'))->toHaveCount(0);
    expect($xpath->query('//nav[@id="site-nav"]//ul[@class="site-nav-account"]'))->toHaveCount(0);
});

test('guests get log in and create account instead of the account menu', function () {
    $this->get(route('portal.information'))
        ->assertOk()
        ->assertDontSee('data-account-menu', false)
        ->assertSeeInOrder(['class="site-nav-account"', route('portal.login'), route('portal.register')], false);
});

test('the account page shows resident information, request progress and account actions once', function () {
    $user = User::factory()->resident()->create();

    $this->get(route('portal.account.home'))->assertRedirect(route('portal.login'));
    $response = $this->actingAs($user, 'resident')->get(route('portal.account.home'))
        ->assertOk()
        ->assertViewIs('portal.account-home')
        ->assertSee($user->resident->full_name)
        ->assertSee($user->resident->resident_number)
        ->assertSee($user->resident->address)
        ->assertSee($user->email);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $main = '//main[@id="resident-main"]';
    expect($xpath->query($main.'//a[@href="'.route('portal.request.create').'"]'))->toHaveCount(1);
    expect($xpath->query($main.'//a[@href="'.route('portal.profile').'"]'))->toHaveCount(1);
    expect($xpath->query($main.'//a[@href="'.route('password.request', ['from' => 'portal']).'"]'))->toHaveCount(1);
    expect($xpath->query($main.'//button[@data-resident-logout]'))->toHaveCount(1);
    expect($xpath->query($main.'//a[@href="'.route('portal.information').'#help"]'))->toHaveCount(0);
});

test('resident log in keeps one help panel with both account paths and a portal password reset link', function () {
    $this->get(route('portal.login'))
        ->assertOk()
        ->assertSee('href="'.route('password.request', ['from' => 'portal']).'"', false)
        ->assertSeeInOrder(['Don&#039;t have an account?', route('portal.register'), route('portal.new-resident.create'), route('portal.information').'#help'], false)
        ->assertDontSee('After logging in');
});

test('the account overview counts requests by stage and points out documents ready to collect', function () {
    $user = User::factory()->resident()->create();
    foreach (['pending', 'processing', 'approved', 'ready_for_release', 'released', 'rejected', 'pending'] as $index => $status) {
        $user->resident->documentRequests()->create([
            'reference_code' => 'REQ-2026-STAT0'.$index, 'source' => 'online', 'document_type' => 'Barangay Clearance',
            'full_name' => $user->resident->full_name, 'address' => $user->resident->address, 'status' => $status,
        ]);
    }

    $response = $this->actingAs($user, 'resident')->get(route('portal.account.home'))->assertOk()
        ->assertSee('A document is ready to collect.');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $counts = collect($xpath->query('//ul[@class="rp-stats"]/li'))
        ->mapWithKeys(fn (DOMElement $item): array => [$item->getAttribute('data-status') => trim($xpath->evaluate('string(.//span[@class="rp-stat-number"])', $item))])
        ->all();

    expect($counts)->toBe(['processing' => '4', 'ready_for_release' => '1', 'released' => '1', 'rejected' => '1']);
    expect($xpath->query('//ul[@class="rp-mini-list"]/li'))->toHaveCount(3);
});

test('the account overview shows an empty state and no ready banner before any request', function () {
    $this->actingAs(User::factory()->resident()->create(), 'resident')->get(route('portal.account.home'))
        ->assertOk()
        ->assertSee('No requests yet')
        ->assertDontSee('A document is ready to collect.');
});

test('form errors are summarised at the top and linked to the field that needs fixing', function () {
    $this->from(route('portal.login'))
        ->post(route('portal.login.store'), ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
        ->assertRedirect(route('portal.login'));

    $this->get(route('portal.login'))
        ->assertSee('data-error-summary', false)
        ->assertSee('data-error-field="email"', false)
        ->assertSee('js/portal-errors.js', false);
});

test('help answers common resident questions using the portal rules', function () {
    $this->get(route('portal.information'))
        ->assertOk()
        ->assertSee('id="faq"', false)
        ->assertSeeInOrder([
            'Where and when do I pay the fee?', 'There is no online payment.',
            'How long is a certificate valid?', 'A Barangay ID is valid for 1 year.',
            'I forgot my password.',
        ]);
});
