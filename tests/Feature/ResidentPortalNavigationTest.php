<?php

use App\CertificateType;
use App\Models\Announcement;
use App\Models\DocumentRequest;
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
        ->assertSeeInOrder(['>Documents and fees</span>', '>Barangay Clearance</span>', '>PHP 25.00</span>', '>Certificate of Residency</span>', '>PHP 25.00</span>', '>Certificate of Indigency</span>', '>Free</span>', '>Business Clearance</span>', '>PHP 200.00</span>', '>To be confirmed</span>'], false)
        ->assertDontSee('PHP 25 ang barangay clearance')
        ->assertDontSee('Regular Barangay ID: PHP 100');
});

test('only an unconfirmed fee carries the to-be-confirmed tag on the portal home', function () {
    $document = new DOMDocument;
    @$document->loadHTML($this->get(route('home'))->assertOk()->getContent());
    $rows = (new DOMXPath($document))->query('//table[contains(@class, "doc-rates-table")]/tbody/tr');

    expect($rows)->toHaveCount(count(CertificateType::cases()));
    foreach ($rows as $row) {
        $type = CertificateType::from(trim($row->getElementsByTagName('th')->item(0)->textContent));
        expect(str_contains($row->textContent, 'To be confirmed'))->toBe(! $type->feeConfirmed(), $type->value);
    }
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
    ])->assertRedirect(route('portal.dashboard'));

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
test('guests and residents get service menus without account pages in them', function () {
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

test('the barangay logo leads guests to the public home and residents to their resident home', function () {
    $logo = function (): string {
        $document = new DOMDocument;
        @$document->loadHTML($this->get(route('portal.information'))->assertOk()->getContent());

        return (new DOMXPath($document))->query('//header//a[contains(@class, "site-brand")]')->item(0)->getAttribute('href');
    };

    expect($logo())->toBe(route('home'));
    $this->actingAs(User::factory()->resident()->create(), 'resident');
    expect($logo())->toBe(route('portal.dashboard'));
    $this->get(route('portal.dashboard'))->assertOk()->assertSee('<title>Home | Barangay Anabu I-G</title>', false);
});

test('english, filipino and the theme toggle stay directly visible in the government bar', function () {
    $document = new DOMDocument;
    @$document->loadHTML($this->get(route('portal.information'))->assertOk()->getContent());
    $xpath = new DOMXPath($document);
    $actions = '//div[contains(@class, "gov-bar")]//div[contains(@class, "gov-actions")]';

    expect(collect($xpath->query($actions.'//button[@data-portal-locale]'))->map(fn (DOMElement $button): string => trim($button->textContent))->all())->toBe(['English', 'Filipino']);
    expect($xpath->query($actions.'//button[@data-resident-theme][@aria-label="Dark mode"][@aria-pressed="false"]'))->toHaveCount(1);
    expect($xpath->query('//*[@data-settings-menu]'))->toHaveCount(0);
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
        ->toBe([route('portal.profile')]);
    expect($xpath->query('.//button[@data-resident-logout]', $list))->toHaveCount(1);
    expect($xpath->query('//nav[@id="site-nav"]//a[@href="'.route('portal.profile').'"]'))->toHaveCount(0);
    expect($xpath->query('//nav[@id="site-nav"]//ul[@class="site-nav-account"]'))->toHaveCount(0);
});

test('guests get log in and create account instead of the account menu', function () {
    $this->get(route('portal.information'))
        ->assertOk()
        ->assertDontSee('data-account-menu', false)
        ->assertSeeInOrder(['class="site-nav-account"', route('portal.login'), route('portal.register')], false);
});

test('my account is one page with resident information, photo upload and password help', function () {
    $user = User::factory()->resident()->create();

    $this->get(route('portal.account.home'))->assertRedirect(route('portal.login'));
    $this->actingAs($user, 'resident')->get(route('portal.account.home'))->assertRedirect(route('portal.profile'));
    $response = $this->get(route('portal.profile'))
        ->assertOk()
        ->assertViewIs('portal.profile')
        ->assertSee('<h1 id="page-title"><span data-portal-i18n="Aking account"', false)
        ->assertSee($user->resident->full_name)
        ->assertSee($user->resident->resident_number)
        ->assertSee($user->resident->address)
        ->assertSee($user->email)
        ->assertSee('JPG, PNG, WEBP, HEIC and other image files, up to 5 MB.');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $main = '//main[@id="resident-main"]';
    expect($xpath->query($main.'//form[@action="'.route('portal.profile.photo.store').'"]//input[@name="photo"][@data-photo-preview]'))->toHaveCount(1);
    expect($xpath->query($main.'//a[@href="'.route('password.request', ['from' => 'portal']).'"]'))->toHaveCount(1);
    expect($xpath->query($main.'//details[@id="corrections"]'))->toHaveCount(1);
    expect(substr_count($response->getContent(), $user->resident->resident_number))->toBe(1);
});

function dashboardRequest(User $user, string $code, string $status, array $attributes = []): DocumentRequest
{
    return $user->resident->documentRequests()->create([
        'reference_code' => $code, 'source' => 'online', 'document_type' => 'Barangay Clearance',
        'full_name' => $user->resident->full_name, 'address' => $user->resident->address, 'status' => $status,
    ] + $attributes);
}

test('an ordinary resident log in opens the dashboard', function () {
    $resident = User::factory()->resident()->create();

    $this->post(route('portal.login.store'), ['email' => $resident->email, 'password' => 'password'])
        ->assertRedirect(route('portal.dashboard'));
    $this->get(route('portal.dashboard'))->assertOk()->assertViewIs('portal.dashboard');
});

test('a resident returns to the portal page that asked them to log in', function () {
    $resident = User::factory()->resident()->create();
    dashboardRequest($resident, 'REQ-2026-INTEND', 'processing');
    $page = route('portal.account.requests.show', 'REQ-2026-INTEND');

    $this->get($page)->assertRedirect(route('portal.login'));
    $this->post(route('portal.login.store'), ['email' => $resident->email, 'password' => 'password'])
        ->assertRedirect($page);
});

test('a resident log in never follows a remembered personnel page', function () {
    $resident = User::factory()->resident()->create();

    $this->withSession(['url.intended' => route('staff.dashboard')])
        ->post(route('portal.login.store'), ['email' => $resident->email, 'password' => 'password'])
        ->assertRedirect(route('portal.dashboard'))
        ->assertSessionHas('url.intended', route('staff.dashboard'));
});

test('the dashboard needs a resident account', function () {
    $this->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('portal.dashboard'))->assertRedirect(route('portal.login'));
});

test('the dashboard lists requests that need attention with what to do next', function () {
    $user = User::factory()->resident()->create();
    dashboardRequest($user, 'REQ-2026-READY1', 'ready_for_release');
    dashboardRequest($user, 'REQ-2026-REJNEW', 'rejected');
    dashboardRequest($user, 'REQ-2026-REJOLD', 'rejected')->forceFill(['updated_at' => now()->subDays(40), 'created_at' => now()->subDays(41)])->saveQuietly();
    dashboardRequest($user, 'REQ-2026-NOTE01', 'processing', ['remarks' => 'Bring your cedula.']);
    dashboardRequest($user, 'REQ-2026-QUIET1', 'pending');

    $response = $this->actingAs($user, 'resident')->get(route('portal.dashboard'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $attention = collect($xpath->query('//section[@class="rp-attention"]//li'))->map(fn (DOMElement $item): string => $item->getAttribute('data-status'))->sort()->values()->all();

    expect($attention)->toBe(['photo', 'processing', 'ready_for_release', 'rejected']);
    $response->assertSee('Ready to collect. Bring your original valid ID and the reference number.')
        ->assertSee('There is a note from the barangay. Read it before you go.')
        ->assertSee('Nothing to do yet. Wait for barangay staff to review it.')
        ->assertSee('2 in progress · 1 ready to collect');
    expect($xpath->query('//ul[@class="rp-recent"]/li'))->toHaveCount(5);
    expect($xpath->query('//section[@class="rp-attention"]//a[contains(@href, "REQ-2026-REJOLD")]'))->toHaveCount(0);
});

test('a resident without requests sees how to make a first request', function () {
    $user = User::factory()->resident()->create();

    $this->actingAs($user, 'resident')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Good day, '.$user->resident->first_name.'.')
        ->assertSee('You have no requests yet. Here is how it works:')
        ->assertSee('Start your first request')
        ->assertDontSee('class="rp-recent"', false)
        ->assertDontSee('ready to collect');
});

test('resident log in keeps one help panel with both account paths and a portal password reset link', function () {
    $this->get(route('portal.login'))
        ->assertOk()
        ->assertSee('href="'.route('password.request', ['from' => 'portal']).'"', false)
        ->assertSeeInOrder(['Don&#039;t have an account?', route('portal.register'), route('portal.new-resident.create'), route('portal.information').'#help'], false)
        ->assertDontSee('After logging in');
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

test('the dashboard shows published barangay announcements only', function () {
    $author = User::factory()->superAdmin()->create();
    $announcement = fn (string $title, string $status, $publishedAt) => Announcement::query()->create([
        'title' => $title, 'body' => $title.' body', 'status' => $status, 'published_at' => $publishedAt, 'created_by' => $author->id,
    ]);
    $published = $announcement('Libreng bakuna sa Sabado', Announcement::STATUS_PUBLISHED, now()->subDay());
    $announcement('Draft na anunsyo', Announcement::STATUS_DRAFT, null);
    $announcement('Archived na anunsyo', Announcement::STATUS_ARCHIVED, now()->subDays(3));
    $announcement('Scheduled na anunsyo', Announcement::STATUS_PUBLISHED, now()->addDay());

    $this->actingAs(User::factory()->resident()->create(), 'resident')->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee('Libreng bakuna sa Sabado')
        ->assertSee(route('portal.announcements.index').'#announcement-'.$published->id, false)
        ->assertDontSee('Draft na anunsyo')
        ->assertDontSee('Archived na anunsyo')
        ->assertDontSee('Scheduled na anunsyo');
});
