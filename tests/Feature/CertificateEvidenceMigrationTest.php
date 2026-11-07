<?php

use App\Models\CertificateEvidence;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('evidence migration recovers interrupted index creation without losing existing photographs', function () {
    Storage::fake('local');
    $evidence = CertificateEvidence::factory()->create();
    Schema::table('certificate_evidence', function (Blueprint $table): void {
        $table->dropUnique('certificate_evidence_uuid_unique');
        $table->dropIndex('certificate_evidence_resident_type_issued_index');
    });

    $migration = require database_path('migrations/2026_11_07_141919_create_certificate_evidence_table.php');
    $migration->up();
    $migration->up();

    $this->assertDatabaseHas('certificate_evidence', ['id' => $evidence->id, 'uuid' => $evidence->uuid]);
    expect(Schema::hasIndex('certificate_evidence', ['resident_id', 'certificate_type', 'issued_certificate_id']))->toBeTrue();
    foreach (Schema::getIndexes('certificate_evidence') as $index) {
        expect(strlen($index['name']))->toBeLessThanOrEqual(64);
    }

    $duplicate = $evidence->getAttributes();
    unset($duplicate['id']);
    expect(fn () => DB::table('certificate_evidence')->insert($duplicate))->toThrow(QueryException::class);
});
