<?php

use App\Models\DocumentRequest;
use App\Models\User;

it('renders accessible public services and branded authentication pages', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('Barangay Anabu I-G')
        ->assertSee('Skip to main content')->assertSee('css/government.css');
})->with(['home', 'login', 'password.request']);

it('rejects a viewer before disclosing records in the dashboard html', function () {
    $this->actingAs(User::factory()->create(['role' => 'viewer']))
        ->get(route('admin.dashboard'))->assertForbidden();
});

it('renders account links and a labeled mobile navigation in the workspace', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))
        ->assertOk()->assertSee('aria-controls="admin-navigation"', false)
        ->assertSee(route('profile.edit'))->assertSee(route('security.edit'))
        ->assertDontSee('All users are verified with biometric authentication');
});

it('renders a paginated request list and rejection reason on its detail page', function () {
    $staff = User::factory()->create();
    $request = DocumentRequest::create([
        'reference_code' => 'REQ-PAGE-001', 'document_type' => 'Barangay Clearance',
        'full_name' => '<script>alert(1)</script>', 'address' => 'Anabu I-G', 'status' => 'pending',
    ]);

    $this->actingAs($staff)->get(route('admin.document-requests.index'))
        ->assertSee('REQ-PAGE-001')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get(route('admin.document-requests.show', $request))
        ->assertSee('name="rejection_reason"', false)->assertSee('standalone-page')
        ->assertDontSee('<option value="released"', false);
});

it('provides branded recovery links for unknown certificate codes', function () {
    $this->get(route('certificate.verify', 'UNKNOWN-CODE'))
        ->assertNotFound()->assertSee('Return to resident services')->assertSee('css/government.css');
});

it('provides mobile input controls and accessible feedback throughout the resident portal', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('viewport-fit=cover', false)
        ->assertSee('inputmode="email"', false)
        ->assertSee('autocomplete="bday"', false)
        ->assertSee('class="status-search-row"', false)
        ->assertSee('id="submit-request-button"', false)
        ->assertSee('id="portal-form-error"', false)
        ->assertSee('Kopyahin ang code')
        ->assertSee('id="resident-details-form"', false);
});
