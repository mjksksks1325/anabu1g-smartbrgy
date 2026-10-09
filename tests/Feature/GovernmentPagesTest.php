<?php

use App\Models\DocumentRequest;
use App\Models\User;

it('selects the Super Admin scrollbar style without applying it to Staff', function (string $route) {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route($route))->assertOk()->assertSee('data-personnel-super-admin="true"', false);

    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'staff']))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route($route))->assertOk()->assertSee('data-personnel-super-admin="false"', false)
        ->assertDontSee('data-personnel-super-admin="true"', false);
})->with(['staff.dashboard', 'staff.rfid-files.index', 'profile.edit']);

it('renders accessible public services and branded authentication pages', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('Barangay Anabu I-G')
        ->assertSee('Skip to main content')->assertSee('css/government.css');
})->with(['home', 'login', 'password.request']);

it('rejects a viewer before disclosing records in the dashboard html', function () {
    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'viewer']))
        ->get(route('staff.dashboard'))->assertForbidden();
});

it('renders account links and a labeled mobile navigation in the workspace', function () {
    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.dashboard'))
        ->assertOk()->assertSee('aria-controls="admin-navigation"', false)
        ->assertSee('css/figma-admin.css')
        ->assertSee('IoT status')
        ->assertDontSee('IoT Not Connected')
        ->assertDontSee('RFID, smart cabinet, and facial recognition are not connected.')
        ->assertSee('aria-controls="logout-dialog"', false)
        ->assertSee('aria-labelledby="logout-title"', false)
        ->assertSee('Log out of SmartBrgy?')
        ->assertSee('data-logout-url="'.route('logout').'"', false)
        ->assertSee(route('profile.edit'))->assertSee(route('security.edit'))
        ->assertDontSee('All users are verified with biometric authentication');

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('staff.dashboard'))
        ->assertSee('Review verified RFID activity and cabinet reports.')
        ->assertDontSee('RFID, smart cabinet, and facial recognition are not connected.');
});

it('keeps super admin navigation and vector symbols across workspace pages', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    foreach (['admin.dashboard', 'admin.smart-cabinet.index', 'admin.cabinet-access.index', 'profile.edit'] as $route) {
        $response = $this->actingAs($superAdmin)->get(route($route))->assertOk();
        $response->assertSee('Dashboard')->assertSee('Smart Cabinet')->assertSee('Employee Cabinet Access')
            ->assertSee('My profile')->assertSee('<svg', false);
    }

    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'staff']))->get(route('staff.dashboard'))
        ->assertOk()->assertDontSee('Employee Cabinet Access');
});

it('scrolls the dashboard menu inside the sidebar while keeping the account footer separate', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('staff.dashboard'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//nav[@id="admin-navigation"]/div[contains(@class, "sidebar-scroll")]')->length)->toBe(1)
        ->and($xpath->query('//nav[@id="admin-navigation"]/div[contains(@class, "sidebar-footer")]')->length)->toBe(1)
        ->and($xpath->query('//nav[@id="admin-navigation"]/div[contains(@class, "sidebar-scroll")]//a[contains(normalize-space(.), "My profile")]')->length)->toBe(1);
});

it('renders a paginated request list and rejection reason on its detail page', function () {
    $staff = User::factory()->assignedOperations()->create();
    $request = DocumentRequest::create([
        'reference_code' => 'REQ-PAGE-001', 'document_type' => 'Barangay Clearance',
        'full_name' => '<script>alert(1)</script>', 'address' => 'Anabu I-G', 'status' => 'pending',
    ]);

    $this->actingAs($staff)->get(route('staff.document-requests.list'))
        ->assertSee('REQ-PAGE-001')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get(route('staff.document-requests.show', $request))
        ->assertSee('name="rejection_reason"', false)->assertSee('class="side-nav"', false)
        ->assertDontSee('<option value="released"', false);
});

it('keeps document request pages inside the staff workspace', function () {
    $staff = User::factory()->assignedOperations()->create(['role' => 'staff']);
    $request = DocumentRequest::create([
        'reference_code' => 'REQ-SHELL-001', 'document_type' => 'Barangay Clearance',
        'full_name' => 'Shell Test', 'address' => 'Anabu I-G', 'status' => 'pending',
    ]);

    $this->actingAs($staff)->get(route('staff.document-requests.index'))->assertOk()
        ->assertSee('Staff workspace')->assertSee('id="admin-navigation"', false)
        ->assertSee('css/figma-admin.css')->assertSee("window.ADMIN_ACTIVE_SCREEN = 'certificates';", false)
        ->assertDontSee('class="civic-masthead"', false);

    foreach (['staff.document-requests.list', 'staff.document-requests.show'] as $route) {
        $url = $route === 'staff.document-requests.show' ? route($route, $request) : route($route);
        $this->actingAs($staff)->get($url)->assertOk()
            ->assertSee('Staff workspace')->assertSee('class="side-nav"', false)
            ->assertSee('css/figma-iot.css')->assertDontSee('class="civic-masthead"', false);
    }
});

it('provides branded recovery links for unknown certificate codes', function () {
    $this->get(route('certificate.verify', 'UNKNOWN-CODE'))
        ->assertNotFound()->assertSee('Return to resident services')->assertSee('css/government.css');
});

it('provides mobile input controls and accessible feedback throughout the resident portal', function () {
    $this->actingAs(User::factory()->resident()->create(), 'resident')->get(route('portal.request.create'))->assertOk()
        ->assertSee('viewport-fit=cover', false)
        ->assertSee('inputmode="email"', false)
        ->assertSee('autocomplete="off"', false)
        ->assertSee('class="status-search-row"', false)
        ->assertSee('id="submit-request-button"', false)
        ->assertSee('id="portal-form-error"', false)
        ->assertSee('Kopyahin ang code')
        ->assertSee('id="resident-details-form"', false);
});

it('keeps staff sign in available without promoting it in the resident header', function () {
    $this->get(route('home'))
        ->assertDontSee('Staff portal')
        ->assertDontSee('href="'.route('login').'"', false);

    $this->get(route('login'))->assertOk()->assertSee('Authorized personnel only');
});

it('keeps settings content beside the sidebar in the Flux layout', function (string $route) {
    $response = $this->actingAs(User::factory()->assignedOperations()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route($route));
    $response->assertOk()->assertSee('href="#account-content"', false);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    /** Flux applies its layout grid to the direct parent of data-flux-main. */
    expect($xpath->query('//body/*[@data-flux-sidebar]')->length)->toBe(1);
    expect($xpath->query('//body/*[@data-flux-main and @id="account-content" and @role="main" and @tabindex="-1"]')->length)->toBe(1);
})->with(['profile.edit', 'security.edit', 'appearance.edit']);

it('keeps every personnel menu icon identical to the RFID sidebar', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->withSession(['auth.password_confirmed_at' => time()]);

    $menuIcons = function (string $route): array {
        $response = $this->get(route($route))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $icons = [];

        foreach ($xpath->query('//nav[@aria-label="Staff navigation"]//*[self::a or self::div][contains(@class, "nav-item")]') as $link) {
            $icon = $xpath->query('./svg', $link)->item(0);
            expect($icon)->not->toBeNull();
            $label = trim(preg_replace('/\s+/', ' ', $link->textContent));
            $label = trim(preg_replace('/\s+0$/', '', $label));
            $icons[$label] = preg_replace('/>\s+</', '><', $document->saveXML($icon));
        }

        return $icons;
    };

    $reference = $menuIcons('staff.rfid-files.index');
    expect($reference)->toHaveCount(16);

    foreach (['staff.dashboard', 'staff.demographics', 'staff.residents.index', 'staff.voters',
        'staff.document-requests.index', 'staff.request-eligibility', 'staff.incidents.index',
        'admin.dashboard', 'admin.audit', 'admin.users.index', 'admin.settings',
        'admin.smart-cabinet.index', 'admin.cabinet-access.index',
        'profile.edit', 'security.edit', 'appearance.edit'] as $route) {
        expect($menuIcons($route))->toEqual($reference);
    }
});

it('shows the workspace account identity in settings without the alternate Flux profile design', function (string $route) {
    $this->actingAs(User::factory()->superAdmin()->create(['name' => 'Super Admin']))
        ->withSession(['auth.password_confirmed_at' => time()]);

    $response = $this->get(route($route))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $footer = '//div[contains(@class, "staff-settings-footer")]';

    expect($xpath->query($footer.'//span[@class="staff-settings-user-avatar"]')->item(0)->textContent)->toBe('SA');
    expect($xpath->query($footer.'//span[@class="staff-settings-user-name"]')->item(0)->textContent)->toBe('Super Admin');
    expect($xpath->query($footer.'//span[@class="staff-settings-user-role"]')->item(0)->textContent)->toBe('admin');
    expect($xpath->query($footer.'//summary[@data-test="sidebar-menu-button"]')->length)->toBe(1);
    expect($xpath->query($footer.'//*[@data-flux-sidebar-profile]')->length)->toBe(0);
    expect($xpath->query($footer.'//form[@method="POST" and @action="'.route('logout').'"]//button[@data-test="logout-button"]')->length)->toBe(1);
})->with(['profile.edit', 'security.edit', 'appearance.edit']);

it('shows the same account initials and role in the IoT workspace', function () {
    $this->actingAs(User::factory()->superAdmin()->create(['name' => 'Super Admin']));

    $response = $this->get(route('staff.rfid-files.index'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//div[@class="side-nav-user"]//span[@class="staff-settings-user-avatar"]')->item(0)->textContent)->toBe('SA');
    expect($xpath->query('//div[@class="side-nav-user"]//span[@class="staff-settings-user-role"]')->item(0)->textContent)->toBe('admin');
});

it('places logout in the bottom account menu with a CSRF protected confirmation on every personnel layout', function (string $route) {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->withSession(['auth.password_confirmed_at' => time()]);

    $response = $this->get(route($route))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//details[@class="personnel-account-menu"]//form[@data-personnel-logout]/button[@aria-controls="logout-dialog"]')->length)->toBe(1);
    expect($xpath->query('//dialog[@id="logout-dialog"]')->length)->toBe(1);
    expect($xpath->query('//dialog[@id="logout-dialog"]//form[@method="POST" and @action="'.route('logout').'"]/input[@name="_token"]')->length)->toBe(1);
    expect($xpath->query('//dialog[@id="logout-dialog"]//button[@type="button" and @data-logout-cancel]')->length)->toBe(2);
    expect($xpath->query('//dialog[@id="logout-dialog"]//button[@type="submit"]')->length)->toBe(1);
    $response->assertSee('Log out of SmartBrgy?');
})->with(['admin.dashboard', 'staff.rfid-files.index', 'profile.edit', 'security.edit']);

it('keeps the audit header and rows inside one scroll container in data order', function () {
    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.audit'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $headers = $xpath->query('//div[@class="audit-log-table-scroll"]/div[@class="audit-table-head"]/span');
    $labels = [];
    foreach ($headers as $header) {
        $labels[] = $header->textContent;
    }

    expect($labels)->toBe(['Type', 'Timestamp', 'Action & Detail', 'User / Source', 'Category']);
    expect($xpath->query('//div[@class="audit-log-table-scroll"]/div[@id="audit-log-list"]')->length)->toBe(1);
});

it('uses one client navigation flow across personnel layouts without changing destination URLs', function (string $route) {
    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route($route))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//nav[@aria-label="Staff navigation"]//a[contains(@class, "nav-item")]');
    expect($links->length)->toBeGreaterThan(0);
    foreach ($links as $link) {
        if ($link->getAttribute('target') === '_blank') {
            expect($link->hasAttribute('wire:navigate'))->toBeFalse();

            continue;
        }
        expect($link->hasAttribute('wire:navigate'))->toBeTrue();
        expect($link->getAttribute('onclick'))->toBe('');
    }
    expect($xpath->query('//script[contains(@src, "/js/personnel-navigation.js") and @data-navigate-once]')->length)->toBe(1);
    foreach ($xpath->query('//script[contains(@src, "/js/admin.js") or contains(@src, "/js/household-profiling.js") or contains(@src, "/js/case-management.js")]') as $script) {
        expect($script->hasAttribute('data-navigate-once'))->toBeTrue();
    }
    $response->assertSee('/livewire-', false);
})->with(['admin.dashboard', 'staff.residents.index', 'admin.audit', 'admin.users.index', 'admin.settings',
    'staff.rfid-files.index', 'admin.smart-cabinet.index', 'admin.cabinet-access.index',
    'profile.edit', 'security.edit', 'appearance.edit']);
