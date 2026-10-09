<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
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
        'My profile',
        'Account security',
        'Portal ng Residente',
    ], false)->assertSee(route('admin.users.index'), false)
        ->assertSee('class="staff-settings-topbar"', false);
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $dashboardLinks = (new DOMXPath($document))->query('//a[@href="'.route('admin.dashboard').'"]');
    expect($dashboardLinks->length)->toBeGreaterThan(0);
    foreach ($dashboardLinks as $dashboardLink) {
        expect($dashboardLink->hasAttribute('wire:navigate'))->toBeTrue();
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
        ->and($links->length)->toBe(16);

    foreach ($links as $link) {
        expect($xpath->query('./svg/*', $link)->length)->toBeGreaterThan(0);
    }

    $activeLink = $xpath->query('.//a[@aria-current="page"]', $navigation->item(0))->item(0);
    expect(trim($activeLink->textContent))->toBe($activeLabel);
})->with([
    ['profile.edit', 'My profile'],
    ['security.edit', 'Account security'],
]);

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

test('user can delete their account', function () {
    $user = User::factory()->assignedOperations()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->assignedOperations()->create();

    $this->actingAs($user);

    $response = Livewire::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
