<?php

use App\Models\IssuedCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The JSON field an archive or delete request needs to pass RequireSupervisorPassword: the password of an
 * active Barangay Captain created for the test.
 *
 * @return array{super_admin_password: string}
 */
function supervisorApproval(): array
{
    User::factory()->create(['role' => 'captain', 'is_active' => true]);

    return ['super_admin_password' => 'password'];
}

/**
 * Read the worksheet XML of a branded .xlsx export so tests can assert on cell text.
 */
function spreadsheetSheetXml(TestResponse $response): string
{
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    return $xml;
}

/** Route mail through a transport that always fails, as an unreachable or refusing SMTP server would. */
function useFailingMailer(): void
{
    Mail::extend('failing', fn (): AbstractTransport => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new TransportException('Connection could not be established with host "127.0.0.1:1".');
        }

        public function __toString(): string
        {
            return 'failing://';
        }
    });
    config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
}

/**
 * A small SVG standing in for a stored QR code: print pages embed the QR file from the public disk.
 */
const FAKE_QR_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 2"><rect width="1" height="1"/></svg>';

/**
 * Puts the certificate's QR code file on a fake public disk, as issuance does, so print tests never touch real storage.
 */
function certificateWithQrFile(IssuedCertificate $certificate): IssuedCertificate
{
    Storage::fake('public');
    Storage::disk('public')->put((string) $certificate->qrStoragePath(), FAKE_QR_SVG);

    return $certificate;
}

/**
 * Every detail an official certificate template can print, as IssueCertificate records it at issuance.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function completeCertificateSnapshot(array $overrides = []): array
{
    return [
        'full_name' => 'Test Resident', 'first_name' => 'Test', 'middle_name' => 'Middle', 'last_name' => 'Resident', 'suffix' => null,
        'address' => 'Historical test address', 'date_of_birth' => '1990-02-12', 'gender' => 'Female', 'civil_status' => 'Single',
        'nationality' => 'Test nationality', 'residing_since' => '2001-07', 'years_of_residency' => 18, 'voter_verified' => false,
        ...$overrides,
    ];
}
