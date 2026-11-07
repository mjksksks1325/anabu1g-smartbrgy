<?php

/*
 * Synthetic data for the browser suite, run through `php artisan tinker` by lib/environment.mjs. It refuses to run
 * unless both the database and the storage folder are inside the suite's own temporary folder (BROWSER_SUITE_DIR).
 * Every person, email address (@test.invalid) and record here is invented.
 */

use App\Models\DocumentRequest;
use App\Models\Incident;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Fortify;

$suite = (string) getenv('BROWSER_SUITE_DIR');
$database = (string) config('database.connections.sqlite.database');
if ($suite === '' || config('database.default') !== 'sqlite' || ! str_starts_with(realpath($database) ?: $database, realpath($suite) ?: $suite) || ! str_starts_with(storage_path(), $suite)) {
    throw new RuntimeException('Refusing to seed: the database and storage must be inside the browser suite folder.');
}

const STAFF_PASSWORD = 'Staff-Passw0rd!2026';
const RESIDENT_PASSWORD = 'Resident-Passw0rd!2026';
const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

// The migrations create the barangay's puroks; make sure the one used here is active.
$purok = Purok::query()->firstOrCreate(['name' => 'Purok 1 - Sampaguita'], ['color' => '#2f855a', 'is_active' => true]);
$purok->forceFill(['is_active' => true])->save();

$portrait = imagecreatetruecolor(300, 360);
imagefill($portrait, 0, 0, (int) imagecolorallocate($portrait, 214, 222, 232));
imagefilledellipse($portrait, 150, 130, 130, 150, (int) imagecolorallocate($portrait, 120, 132, 148));
ob_start();
imagejpeg($portrait, null, 85);
Storage::disk('local')->put('resident-photos/browser-suite-portrait.jpg', (string) ob_get_clean());

$resident = function (string $lastName, array $attributes = []) use ($purok): Resident {
    return Resident::factory()->create([
        'first_name' => 'Testa', 'middle_name' => 'Synthetic', 'last_name' => $lastName, 'suffix' => null,
        'address' => '7 Rizal St., Anabu I-G, City of Imus, Cavite', 'purok' => $purok->name, 'date_of_birth' => '1985-03-14',
        'gender' => 'Female', 'civil_status' => 'Single', 'nationality' => 'Filipino', 'status' => 'active',
        'is_in_good_standing' => true, 'is_verified_indigent' => true, 'photo_path' => 'resident-photos/browser-suite-portrait.jpg',
        ...$attributes,
    ]);
};
$account = function (string $email, Resident $person): User {
    $user = User::factory()->create(['email' => $email, 'name' => $person->full_name, 'role' => 'resident', 'resident_id' => $person->id, 'email_verified_at' => now()]);
    $user->forceFill(['password' => RESIDENT_PASSWORD])->save();

    return $user;
};
$staff = function (string $email, string $role, ?array $permissions, bool $superAdmin = false, bool $twoFactor = false): User {
    $user = User::factory()->create(['email' => $email, 'name' => 'Browser '.ucfirst(strtok($email, '@')), 'email_verified_at' => now()]);
    $user->forceFill([
        'password' => STAFF_PASSWORD, 'role' => $role, 'is_super_admin' => $superAdmin, 'is_active' => true, 'staff_permissions' => $permissions,
        ...($twoFactor ? [
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(TWO_FACTOR_SECRET),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['browser-recovery-1', 'browser-recovery-2'])),
            'two_factor_confirmed_at' => now(),
        ] : []),
    ])->save();

    return $user;
};

// Residents: one awaiting portal activation with a staff-issued code, and confirmed accounts for the portal flows.
$activationCode = 'BRWS'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
$activation = $resident('Activation', ['nationality' => null]);
$activation->forceFill(['portal_registration_hash' => hash('sha256', $activationCode), 'portal_registration_expires_at' => now()->addDay(), 'portal_registration_sent_at' => null])->save();
$complete = $resident('Complete');
VoterRegistration::factory()->create(['resident_id' => $complete->id, 'status' => 'active']);
$requester = $account('resident@test.invalid', $complete);
$account('reset-resident@test.invalid', $resident('Resetter'));
$account('rate-a@test.invalid', $resident('Ratea'));
$account('rate-b@test.invalid', $resident('Rateb'));

// Staff: a super admin with a known two-factor secret, a privileged clerk who has not set up two-factor yet, a
// certificate clerk without privileged access, and an incident desk without VAWC access.
$staff('admin@test.invalid', 'admin', null, superAdmin: true, twoFactor: true);
$staff('records@test.invalid', 'staff', ['dashboard.view', 'records.view', 'records.update']);
$staff('clerk@test.invalid', 'staff', ['dashboard.view', 'documents.view', 'documents.print']);
$staff('desk@test.invalid', 'staff', ['dashboard.view', 'incidents.view', 'incidents.submit']);

$ready = DocumentRequest::query()->create([
    'reference_code' => 'REQ-BROWSER-READY', 'resident_id' => $complete->id, 'source' => 'online', 'document_type' => 'Barangay Clearance',
    'full_name' => $complete->full_name, 'address' => $complete->address, 'purpose' => 'Employment requirement', 'status' => 'ready_for_release',
    'email' => $requester->email,
]);
$vawc = Incident::factory()->create(['incident_type' => 'VAWC', 'is_sensitive' => true, 'complainant_name' => 'Confidential Complainant']);
$ordinary = Incident::factory()->create(['incident_type' => 'Noise Complaint', 'is_sensitive' => false]);

echo json_encode([
    'staffPassword' => STAFF_PASSWORD, 'residentPassword' => RESIDENT_PASSWORD, 'twoFactorSecret' => TWO_FACTOR_SECRET,
    'activation' => ['residentNumber' => $activation->resident_number, 'code' => $activationCode, 'residentId' => $activation->id],
    'complete' => ['residentId' => $complete->id], 'readyRequest' => ['id' => $ready->id, 'code' => $ready->reference_code],
    'incidents' => ['vawc' => $vawc->id, 'ordinary' => $ordinary->id],
]), PHP_EOL;
