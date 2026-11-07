<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Livewire\Exceptions\ComponentNotFoundException;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->assignedOperations()->create());

    $this->get('/staff/settings/profile')->assertOk();
});

test('staff settings returns to the admin dashboard through consistent Livewire navigation', function () {
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)->get(route('profile.edit'));

    $response->assertSeeInOrder([
        'js/staff-settings-theme.js',
        'window.Flux.applyAppearance',
        'Dashboard',
        'Demographics',
        'Resident Records',
        'Voters',
        'Certificates &amp; Clearances',
        'Request Eligibility',
        'Incident Reports',
        'RFID File Tracking',
        'Smart Cabinet',
        'Employee Cabinet Access',
        'Audit Log',
        'User Management',
        'Settings',
        'Resident Portal',
    ], false)->assertSee(route('admin.users.index'), false)
        ->assertSee('class="staff-settings-topbar"', false);
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $dashboardLinks = (new DOMXPath($document))->query('//a[@href="'.route('admin.dashboard').'"]');
    expect($dashboardLinks->length)->toBeGreaterThan(0);
    foreach ($dashboardLinks as $dashboardLink) {
        expect($dashboardLink->hasAttribute('wire:navigate') || $dashboardLink->hasAttribute('wire:navigate.hover'))->toBeTrue();
    }
});

test('profile and security use the dashboard style sidebar with the same navigation icons', function (string $route, string $activeLabel) {
    $response = $this->actingAs(User::factory()->superAdmin()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route($route))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $navigation = $xpath->query('//nav[contains(concat(" ", normalize-space(@class), " "), " staff-settings-navigation ")]');
    $links = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " nav-item ")]', $navigation->item(0));

    expect($navigation->length)->toBe(1)
        ->and($links->length)->toBe(15);

    foreach ($links as $link) {
        expect($xpath->query('./svg/*', $link)->length)->toBeGreaterThan(0);
    }

    $activeLink = $xpath->query('.//a[@aria-current="page"]', $navigation->item(0))->item(0);
    expect(trim($activeLink->textContent))->toBe($activeLabel);
})->with([
    ['profile.edit', 'Settings'],
    ['security.edit', 'Settings'],
    ['appearance.edit', 'Settings'],
]);

test('employees without administrative access reach their own settings from every sidebar', function () {
    $staff = User::factory()->assignedOperations()->create();
    $settingsLink = '//nav//a[contains(concat(" ", normalize-space(@class), " "), " nav-item ") and @href="'.route('profile.edit').'"]';

    foreach (['staff.dashboard', 'staff.rfid-files.index', 'profile.edit', 'security.edit'] as $route) {
        $response = $this->actingAs($staff)->withSession(['auth.password_confirmed_at' => time()])->get(route($route))->assertOk();
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new DOMXPath($document))->query($settingsLink);

        expect($links->length)->toBe(1, $route)
            ->and(trim($links->item(0)->textContent))->toBe('Settings');
        if (in_array($route, ['profile.edit', 'security.edit'], true)) {
            expect($links->item(0)->getAttribute('aria-current'))->toBe('page', $route);
        }
    }

    $this->actingAs($staff)->get(route('admin.settings'))->assertForbidden();
});

test('each settings page marks its own section in the settings menu', function (string $route) {
    $response = $this->actingAs(User::factory()->assignedOperations()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route($route))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $current = (new DOMXPath($document))->query('//*[@aria-label="Settings" and @data-flux-navlist]//a[@data-current]');

    expect($current->length)->toBe(1)
        ->and($current->item(0)->getAttribute('href'))->toBe(route($route));
})->with(['profile.edit', 'security.edit', 'appearance.edit']);

test('staff appearance offers explicit light and dark choices', function () {
    $admin = User::factory()->superAdmin()->create();

    $response = $this->actingAs($admin)->get(route('appearance.edit'));

    $response->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('smartbrgy_theme', false)
        ->assertDontSee('System');
});

test('staff settings hides super admin navigation from other employees', function () {
    $staff = User::factory()->assignedOperations()->create();

    $response = $this->actingAs($staff)->get(route('profile.edit'));

    $response->assertSee('RFID File Tracking')
        ->assertSee('Incident Reports')
        ->assertDontSee('Employee Cabinet Access');
});

test('profile information can be updated', function () {
    $user = User::factory()->assignedOperations()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->assignedOperations()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('staff settings no longer offer account deletion to any personnel role', function (string $role) {
    $user = match ($role) {
        'super admin' => User::factory()->superAdmin()->create(),
        'captain' => User::factory()->create(['role' => 'captain', 'is_active' => true]),
        default => User::factory()->assignedOperations()->create(['role' => 'staff']),
    };

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Delete account')
        ->assertDontSee('confirm-user-deletion')
        ->assertSee('Staff accounts are removed in User Management');

    expect($user->fresh())->not->toBeNull()->and($user->fresh()->trashed())->toBeFalse();
})->with(['staff', 'captain', 'super admin']);

test('the self-deletion endpoint no longer exists, so a direct Livewire call cannot delete an account', function (string $role) {
    $user = $role === 'super admin'
        ? User::factory()->superAdmin()->create(['password' => 'own-password-123'])
        : User::factory()->create(['role' => $role, 'is_active' => true, 'password' => 'own-password-123']);
    User::factory()->create(['role' => 'captain', 'is_active' => true, 'password' => 'captain-password-123']);

    $this->actingAs($user);

    expect(fn () => Livewire::test('settings.delete-user-form')->set('password', 'captain-password-123')->call('deleteUser'))
        ->toThrow(ComponentNotFoundException::class);
    expect(class_exists('App\Livewire\Settings\DeleteUserForm'))->toBeFalse()
        ->and($user->fresh())->not->toBeNull()
        ->and(auth()->check())->toBeTrue();
})->with(['staff', 'captain', 'super admin']);
