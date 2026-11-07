<?php

use App\Models\CabinetDevice;
use App\Models\CabinetFile;
use App\Models\EmployeeCabinetAccess;
use App\Models\User;
use App\PortalLocalization;

it('takes the hidden page title out of the phone header so no empty band is left under the language row', function () {
    $css = file_get_contents(public_path('css/staff-theme.css'));
    $phoneMenu = substr($css, strpos($css, '/* ── IoT/request pages: phone menu'));

    expect($phoneMenu)->toContain('@media (max-width: 650px)')
        ->toContain('body[data-personnel-layout="iot"] .workspace-title { display: none; }');
});

it('translates the smart cabinet fallbacks and keeps device-reported text as data', function () {
    CabinetDevice::factory()->create(['identifier' => 'CAB-01', 'last_error' => null]);
    CabinetDevice::factory()->create(['identifier' => 'CAB-02', 'last_error' => 'Reader timeout on bus 2']);

    $content = $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.smart-cabinet.index'))->assertOk()->getContent();

    expect($content)->toContain('<span data-no-admin-translate>Reader timeout on bus 2</span>')
        ->toMatch('/<dd[^>]*>\s*None reported\s*<\/dd>/')
        ->not->toContain('data-no-admin-translate>None reported');
});

it('writes identifier patterns that browsers can compile with the unicode-sets flag', function () {
    CabinetDevice::factory()->create();
    $content = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.smart-cabinet.index'))->assertOk()->getContent();

    expect($content)->toContain('pattern="[A-Za-z0-9][A-Za-z0-9._\-]*"')
        ->toContain('pattern="[A-Za-z0-9][A-Za-z0-9._\/\-]*"')
        ->not->toContain('._-]*"')
        ->not->toContain('._/-]*"');
});

it('puts each static fragment of the cabinet access details in its own translatable text', function () {
    $employee = User::factory()->create(['role' => 'staff', 'email' => 'records@example.test']);
    EmployeeCabinetAccess::factory()->create(['user_id' => $employee->id, 'is_active' => true]);

    $content = $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.cabinet-access.index'))->assertOk()->getContent();

    expect($content)->toContain('<span>Employee #'.$employee->id.'</span>')
        ->toContain('<span data-no-admin-translate>records@example.test</span> <span aria-hidden="true">&middot;</span> <span>Staff</span>')
        ->toContain('<span>No completion date</span>')
        ->toMatch('/data-ui-initial-focus[^>]*>\s*Cancel/');
});

it('answers a wrong supervisor password in the staff interface language', function (?string $locale, string $message) {
    $folder = CabinetFile::factory()->create();
    $request = $this->actingAs(User::factory()->superAdmin()->create());
    if ($locale) {
        $request = $request->withCredentials()->withCookie(PortalLocalization::ADMIN_COOKIE, $locale);
    }

    $request->postJson(route('admin.smart-cabinet.folders.archive', $folder), ['super_admin_password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.super_admin_password.0', $message);
    expect($folder->fresh()->is_active)->toBeTrue();
})->with([
    'English' => [null, 'The active Barangay Captain or Super Admin password is incorrect.'],
    'Filipino' => ['fil', 'Hindi tama ang password ng active Barangay Captain o Super Admin.'],
]);

it('confirms an archived folder on the page that reloads after the archive', function () {
    $folder = CabinetFile::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->postJson(route('admin.smart-cabinet.folders.archive', $folder), ['super_admin_password' => 'password'])
        ->assertOk()
        ->assertSessionHas('status', 'Folder archived. Its movement history is retained.');
    expect($folder->fresh()->is_active)->toBeFalse();
});

it('has an English and a Filipino catalog entry for every label Work Group 3 made translatable', function (string $key) {
    $english = json_decode(file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
    $filipino = json_decode(file_get_contents(lang_path('fil.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($english)->toHaveKey($key)
        ->and($filipino)->toHaveKey($key)
        ->and($filipino[$key])->not->toBe('');
})->with([
    ':count registered', ':count applications', ':count requests', ':count folders', 'Submitted :date', 'Employee #:id',
    'Updated :date', 'Never updated', 'No completion date', 'Request RFID enrollment', 'Request face enrollment',
    'Online', 'Offline', 'Connected', 'Disconnected', 'Not current', ':healthy of :total components healthy',
    'Log out of SmartBrgy?', 'Please confirm your password before opening Account Security.',
    'The active Barangay Captain or Super Admin password is incorrect.', 'Could not reach the server. No folder was changed.',
]);
