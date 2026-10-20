<?php

use App\Models\User;

test('missing pages retain the branded accessible recovery page', function () {
    $response = $this->get('/missing-interface-review-page')->assertNotFound();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($document->getElementById('main-content'))->not->toBeNull();
    expect($xpath->query('//a[@href="'.route('home').'" and contains(., "Return to resident services")]')->length)->toBe(1);
    $response->assertSee('Barangay Anabu I-G')->assertSee('css/figma-tokens.css');
});

test('document request summary is a keyboard-accessible region containing every status card', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('staff.document-requests.index'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $region = $xpath->query('//*[@aria-label="Document request summary"]')->item(0);
    expect($region)->not->toBeNull();
    expect($region->getAttribute('role'))->toBe('region');
    expect($region->getAttribute('tabindex'))->toBe('0');
    expect($xpath->query('./div[contains(@class, "stat-card")]', $region)->length)->toBe(4);
});

test('demographics provides a named keyboard-accessible scrolling region for senior records', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('staff.demographics'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $region = $document->getElementById('senior-citizens-list');
    expect($region)->not->toBeNull();
    expect($region->getAttribute('class'))->toContain('table-scroll');
    expect($region->getAttribute('tabindex'))->toBe('0');
    expect($region->getAttribute('role'))->toBe('region');
    expect($region->getAttribute('aria-label'))->toBe('Senior citizens register');
});

test('incident upload instructions match the image-only picker', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('staff.incidents.index'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $input = $xpath->query('//input[@id="inc-attachments"]')->item(0);
    expect($input)->not->toBeNull();
    expect($input->getAttribute('accept'))->toContain('.jpg,.jpeg,.png,.gif,.bmp,.webp,.avif,.heic,.heif', 'image/jpeg,image/png,image/gif,image/bmp,image/webp,image/avif,image/heic,image/heif');
    $label = $xpath->query('//input[@id="inc-attachments"]/preceding-sibling::div[1]')->item(0);
    expect($label->textContent)->toContain('supported image files only', '5 MB')->not->toContain('PDF');
});
