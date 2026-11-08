<?php

use App\CertificateType;
use App\Models\DocumentRequest;
use App\Models\User;
use App\PortalLocalization;

test('resident portal footer links to the Provincial Government of Cavite', function () {
    $response = $this->get(route('portal.officials'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//footer//nav[@id="government-links"]//a[@href="https://cavite.gov.ph/"]');

    expect($links)->toHaveCount(1);
    expect(trim($links->item(0)->textContent))->toBe('Provincial Government of Cavite');
    expect($links->item(0)->getAttribute('target'))->toBe('_blank');
    expect($links->item(0)->getAttribute('rel'))->toBe('noopener noreferrer');
});

test('resident portal footer groups barangay contact, quick links and government links without unconfirmed details', function () {
    $response = $this->get(route('portal.officials'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $footer = '//footer[contains(@class, "site-footer")]';

    expect($xpath->query($footer.'//section[@id="footer-contact"]//a[starts-with(@href, "https://www.google.com/maps/search/")][@target="_blank"][@rel="noopener noreferrer"]'))->toHaveCount(1);
    expect($xpath->query($footer.'//section[@id="footer-contact"]//*[contains(@class, "tag-unconfirmed")]'))->toHaveCount(0);
    expect($xpath->query($footer.'//section[@id="footer-contact"]//a[@href="tel:911"]'))->toHaveCount(1);
    expect(collect($xpath->query($footer.'//nav[@id="quick-links"]//a'))->map(fn (DOMElement $link): string => $link->getAttribute('href'))->all())
        ->toBe([
            route('portal.request.create'),
            route('portal.information').'#requirements',
            route('portal.announcements.index'),
            route('portal.officials'),
            route('home').'#contacts',
            route('portal.information').'#help',
        ]);
    expect($xpath->query($footer.'//nav[@id="government-links"]//a[@target="_blank"][@rel="noopener noreferrer"]'))->toHaveCount(5);
    expect($xpath->query($footer.'//a[contains(@href, "facebook.com")]'))->toHaveCount(0);
});

test('officials placeholders render with local images and native details controls', function () {
    $response = $this->get(route('portal.officials'));

    $response->assertOk()
        ->assertSee('Barangay Official')
        ->assertSee('Position')
        ->assertSee('Term of Office')
        ->assertSee('View Details')
        ->assertSee(asset('images/official-placeholder.svg'), false)
        ->assertDontSee('Hinihintay pa ang opisyal na listahan');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//section[@id="officials"]//article'))->toHaveCount(4);
    expect($xpath->query('//section[@id="officials"]//details/summary'))->toHaveCount(4);
    expect(is_file(public_path('images/official-placeholder.svg')))->toBeTrue();
});

test('official photos fall back when absent empty or missing locally', function (?string $photoPath) {
    config(['portal.officials' => [[
        'photo_path' => $photoPath,
        'full_name' => 'Barangay Official',
        'position' => 'Position',
        'term' => 'Term of Office',
    ]]]);

    $this->get(route('portal.officials'))
        ->assertSee('src="'.asset('images/official-placeholder.svg').'"', false);
})->with([null, '', 'images/missing-official-photo.jpg']);

test('an available local official photo can replace the placeholder', function () {
    config(['portal.officials' => [[
        'photo_path' => 'images/anabu-logo.jpg',
        'full_name' => 'Barangay Official',
        'position' => 'Position',
        'term' => 'Term of Office',
    ]]]);

    $this->get(route('portal.officials'))
        ->assertSee('src="'.asset('images/anabu-logo.jpg').'"', false)
        ->assertDontSee('images/official-placeholder.svg');
});

test('official names positions and terms are escaped', function () {
    config(['portal.officials' => [[
        'photo_path' => null,
        'full_name' => '<script>alert("name")</script>',
        'position' => '<img src=x onerror=alert("position")>',
        'term' => '<svg onload=alert("term")>',
    ]]]);

    $this->get(route('portal.officials'))
        ->assertSee('<script>alert("name")</script>')
        ->assertSee('<img src=x onerror=alert("position")>')
        ->assertSee('<svg onload=alert("term")>')
        ->assertDontSee('<script>alert("name")</script>', false)
        ->assertDontSee('<img src=x onerror=alert("position")>', false)
        ->assertDontSee('<svg onload=alert("term")>', false);
});

test('information links to the separate public officials page without repeating its cards', function () {
    $this->get(route('portal.information'))
        ->assertSee('href="'.route('portal.officials').'"', false)
        ->assertDontSee('images/official-placeholder.svg')
        ->assertDontSee('View Details');

    $this->get(route('portal.officials'))
        ->assertOk()
        ->assertViewIs('portal.officials')
        ->assertSee('href="'.route('portal.information').'"', false)
        ->assertSee('Barangay officials');
});

test('confirmed fees are shown on information and authenticated request pages', function () {
    $this->get(route('portal.information'))
        ->assertSeeInOrder(['Barangay Clearance', 'PHP 25.00', 'Certificate of Residency', 'PHP 25.00', 'Certificate of Indigency', 'Walang bayad', 'First Time Jobseeker', 'Walang bayad'])
        ->assertDontSee('PHP 50.00');

    $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->get(route('portal.request.create'))
        ->assertSeeInOrder(['Barangay Clearance', 'PHP 25.00', 'Certificate of Residency', 'PHP 25.00', 'Certificate of Indigency', 'Walang bayad', 'First Time Jobseeker', 'Walang bayad'])
        ->assertDontSee('PHP 50.00');
});

test('each requestable document shows its system fee and certificate validity', function () {
    $response = $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->get(route('portal.request.create'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $options = $xpath->query('//div[@id="cert-type-grid"]/button[contains(@class, "cert-btn")]');

    expect($xpath->query('//*[@id="screen-doctype"]//*[contains(@class, "v2-callout-warning") and normalize-space(.)=""]'))->toHaveCount(0);

    expect($options)->toHaveCount(count(CertificateType::cases()));
    foreach (CertificateType::cases() as $index => $type) {
        $text = preg_replace('/\s+/', ' ', $options->item($index)->textContent);
        expect($text)->toContain($type->value, $type->feeLabel() === 'Walang bayad' ? 'Free' : $type->feeLabel())
            ->toContain(in_array($type, [CertificateType::BarangayId, CertificateType::FirstTimeJobseeker], true) ? 'Valid for 1 year from issue' : 'Valid for 3 months from issue');
        expect($text)->not->toContain('To be confirmed');
    }
});

test('request terms keep the full legal text after a plain summary', function () {
    $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->get(route('portal.request.create'))->assertOk()
        ->assertSeeInOrder(['What you are agreeing to, in short', 'id="tnc-scroll"', 'Online requests remain valid for 30 days from submission.', 'Monday to Friday, 8:00 AM to 5:00 PM.']);
});

test('signed-in portal pages show office hours and fees without confirmation labels', function (string $route) {
    $user = User::factory()->resident()->create();
    $parameters = [];
    if ($route === 'portal.account.requests.show') {
        $request = DocumentRequest::query()->create([
            'resident_id' => $user->resident_id, 'reference_code' => 'REQ-PORTAL-BUSINESS',
            'source' => 'online', 'document_type' => CertificateType::BusinessClearance->value,
            'full_name' => $user->name, 'address' => 'Anabu I-G, Imus City', 'status' => 'pending',
        ]);
        $parameters = [$request->reference_code];
    }

    $this->actingAs($user, 'resident')->get(route($route, $parameters))->assertOk()
        ->assertDontSee('data-portal-i18n="Hindi pa kumpirmado"', false)
        ->assertDontSee('data-portal-i18n="Hindi pa kumpirmado:"', false)
        ->assertDontSee('data-portal-i18n="To be confirmed"', false)
        ->assertDontSee('data-portal-i18n="Pending verification"', false);
})->with(['portal.dashboard', 'portal.profile', 'portal.request.create', 'portal.account.requests.show']);

test('home and help show the supplied land area without verification labels in either language', function (string $route, string $locale) {
    $this->withCookie(PortalLocalization::COOKIE, $locale)->get(route($route))
        ->assertSeeInOrder([$locale === 'fil' ? 'Lawak ng Lupa' : 'Land Area', '183.07', $locale === 'fil' ? 'ektarya' : 'hectares'])
        ->assertDontSee('Pending verification')
        ->assertDontSee('To be confirmed')
        ->assertDontSee('Hinihintay ang beripikasyon')
        ->assertDontSee('Hindi pa kumpirmado')
        ->assertSee('src="https://maps.google.com/maps?q=Barangay%20Anabu%20I-G%2C%20Imus%2C%20Cavite&amp;output=embed"', false);
})->with(['home', 'portal.information'])->with(['en', 'fil']);

test('official resources link to the supplied government and assistance websites', function () {
    $response = $this->get(route('portal.information'));

    $response->assertSee("8888 Citizens' Complaint Hotline")
        ->assertSee('8888')
        ->assertSee('Provincial Government of Cavite')
        ->assertSee('Medical Assistance')
        ->assertSee('Burial Assistance')
        ->assertDontSee('Official link pending verification')
        ->assertDontSee('Official link to be confirmed');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//section[@aria-labelledby="resources-title"]//a');
    expect($links)->toHaveCount(4);
    $destinations = [
        'https://8888.gov.ph/',
        'https://cavite.gov.ph/',
        'https://cavite.gov.ph/social-welfare-services/',
        'https://cavite.gov.ph/social-welfare-services/',
    ];
    foreach ($links as $index => $link) {
        expect($link->getAttribute('href'))->toBe($destinations[$index]);
        expect($link->getAttribute('target'))->toBe('_blank');
        expect($link->getAttribute('rel'))->toBe('noopener noreferrer');
    }
});
