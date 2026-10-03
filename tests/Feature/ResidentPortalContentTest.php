<?php

use App\Models\User;

test('resident portal footer links to the Provincial Government of Cavite', function () {
    $response = $this->get(route('portal.officials'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//footer//nav[@id="quick-links"]//a[@href="https://cavite.gov.ph/"]');

    expect($links)->toHaveCount(1);
    expect(trim($links->item(0)->textContent))->toBe('Provincial Government of Cavite');
    expect($links->item(0)->getAttribute('target'))->toBe('_blank');
    expect($links->item(0)->getAttribute('rel'))->toBe('noopener noreferrer');
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

test('home displays an unconfirmed land area without changing the map', function () {
    $this->get(route('home'))
        ->assertSeeInOrder(['Land Area', 'To be confirmed'])
        ->assertSee('src="https://maps.google.com/maps?q=Barangay%20Anabu%20I-G%2C%20Imus%2C%20Cavite&amp;output=embed"', false);
});

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
