<?php

use App\Actions\ReadVotersCsv;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

function votersCsvFile(array $rows, bool $bom = false): UploadedFile
{
    $stream = fopen('php://temp', 'r+');
    if ($bom) {
        fwrite($stream, "\xEF\xBB\xBF");
    }
    fputcsv($stream, ReadVotersCsv::HEADERS, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($stream, $row, ',', '"', '');
    }
    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);

    return UploadedFile::fake()->createWithContent('voters.csv', $contents);
}

function votersCsvRow(Resident $resident, string $number = '1234-5678-9012'): array
{
    return [$resident->resident_number, $number, '0123A', '045', '2026-05-12'];
}

test('personnel can import voters linked by exact resident numbers with encryption and audit', function () {
    $this->travelTo('2026-10-04');
    $staff = User::factory()->create();
    $first = Resident::factory()->create(['date_of_birth' => '1990-01-01']);
    $second = Resident::factory()->create(['date_of_birth' => '1992-01-01']);

    $this->actingAs($staff)->post(route('admin.voter-registrations.import'), [
        'file' => votersCsvFile([votersCsvRow($first), votersCsvRow($second, '9999-8888-7777')], true),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('imported', 2)
        ->assertJsonMissing(['comelec_voter_number' => '1234-5678-9012']);

    $this->assertDatabaseCount('residents', 2);
    $this->assertDatabaseCount('voter_registrations', 2);
    $this->assertDatabaseCount('voter_registration_audits', 2);
    $this->assertDatabaseHas('voter_registrations', ['resident_id' => $first->id, 'created_by' => $staff->id]);
    expect(DB::table('voter_registrations')->where('resident_id', $first->id)->value('comelec_voter_number'))->not->toBe('1234-5678-9012');
    expect(VoterRegistration::query()->firstOrFail()->integrity_valid)->toBeTrue();
});

test('a bad voter csv row rolls back every imported record and its audit', function (string $problem) {
    $this->travelTo('2026-10-04');
    $staff = User::factory()->create();
    $first = Resident::factory()->create(['date_of_birth' => '1990-01-01']);
    $second = Resident::factory()->create(['date_of_birth' => '1992-01-01']);
    $row = votersCsvRow($second, '9999-8888-7777');
    if ($problem === 'unknown') {
        $row[0] = 'UNKNOWN-RESIDENT';
    } elseif ($problem === 'duplicate resident') {
        $row[0] = $first->resident_number;
    } elseif ($problem === 'duplicate number') {
        $row[1] = '1234-5678-9012';
    } elseif ($problem === 'invalid date') {
        $row[4] = '10/04/2026';
    } elseif ($problem === 'future date') {
        $row[4] = '2027-01-01';
    } elseif ($problem === 'inactive') {
        $second->update(['status' => 'inactive']);
    } elseif ($problem === 'underage') {
        $second->update(['date_of_birth' => '2015-01-01']);
    } elseif ($problem === 'archived') {
        $second->delete();
    }

    $this->actingAs($staff)->post(route('admin.voter-registrations.import'), [
        'file' => votersCsvFile([votersCsvRow($first), $row]),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file')
        ->assertJsonPath('errors.file.0', fn (string $message): bool => str_starts_with($message, 'CSV row 3:'));

    $this->assertDatabaseCount('voter_registrations', 0);
    $this->assertDatabaseCount('voter_registration_audits', 0);
})->with(['unknown', 'duplicate resident', 'duplicate number', 'invalid date', 'future date', 'inactive', 'underage', 'archived']);

test('voter csv import does not overwrite existing voters', function () {
    $staff = User::factory()->create();
    $resident = Resident::factory()->create(['date_of_birth' => '1990-01-01']);
    $existing = VoterRegistration::factory()->for($resident)->create(['precinct_number' => 'OLD']);

    $this->actingAs($staff)->post(route('admin.voter-registrations.import'), [
        'file' => votersCsvFile([votersCsvRow($resident)]),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

    expect($existing->fresh()->precinct_number)->toBe('OLD');
    $this->assertDatabaseCount('voter_registrations', 1);
});

test('voter import rejects invalid files and missing headers', function (string $contents) {
    $staff = User::factory()->create();

    $this->actingAs($staff)->post(route('admin.voter-registrations.import'), [
        'file' => UploadedFile::fake()->createWithContent('voters.csv', $contents),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

    $this->assertDatabaseCount('voter_registrations', 0);
})->with(['', 'name,birthday', implode(',', ReadVotersCsv::HEADERS)."\n", implode(',', ReadVotersCsv::HEADERS)."\na,b"]);

test('voter import refuses oversized files and more than 500 rows', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff)->post(route('admin.voter-registrations.import'), [
        'file' => UploadedFile::fake()->create('voters.csv', 5121, 'text/csv'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

    $this->post(route('admin.voter-registrations.import'), [
        'file' => votersCsvFile(array_fill(0, 501, ['RES-1', '123456', 'P1', '1', '2026-05-12'])),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseCount('voter_registrations', 0);
});

test('only personnel can download the voter import template or import voters', function () {
    $this->getJson(route('admin.voter-registrations.template'))->assertUnauthorized();
    $this->postJson(route('admin.voter-registrations.import'))->assertUnauthorized();
    $resident = User::factory()->resident()->create();
    $this->actingAs($resident)->getJson(route('admin.voter-registrations.template'))->assertForbidden();
    $this->postJson(route('admin.voter-registrations.import'))->assertForbidden();
    $staff = User::factory()->create();
    $this->actingAs($staff)->get(route('admin.voter-registrations.template'))
        ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="voters-import-template.csv"')
        ->assertContent(implode(',', ReadVotersCsv::HEADERS)."\r\n");
});
