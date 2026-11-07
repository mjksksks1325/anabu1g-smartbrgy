<?php

use App\CertificateType;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['app.debug' => true]);
});

test('a database schema error during Print & Release shows a generic message with a reference, never SQL or file paths', function () {
    Storage::fake('public');
    Log::spy();
    // Reproduces the reported failure: a database that has not run the template_version migration.
    Schema::table('issued_certificates', fn (Blueprint $table) => $table->dropColumn('template_version'));
    $request = DocumentRequest::query()->create([
        'reference_code' => 'REQ-DB-ERROR', 'document_type' => CertificateType::BarangayClearance->value,
        'full_name' => 'Private Resident Name', 'address' => 'Private Address 123', 'purpose' => 'Employment', 'status' => 'ready_for_release',
    ]);

    $response = $this->actingAs(User::factory()->assignedOperations()->create())
        ->postJson(route('staff.document-requests.issue', $request))
        ->assertStatus(500)
        ->assertJsonStructure(['message', 'reference']);

    $body = $response->getContent();
    foreach (['SQLSTATE', 'template_version', 'issued_certificates', 'insert into', 'Private Resident Name', '.sqlite', base_path(), 'Stack trace', 'vendor'] as $secret) {
        expect(str_contains($body, $secret))->toBeFalse("The response exposed: {$secret}");
    }
    expect($response->json('message'))->toContain($response->json('reference'));
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => str_contains($message, $response->json('reference')) && str_contains($context['exception']->getMessage(), 'template_version'))->once();
    expect($request->fresh()->status)->toBe('ready_for_release');
});

test('database errors on pages show the generic error page instead of the debug screen', function () {
    Route::middleware('web')->get('/__database-error-probe', fn () => DB::select('select * from a_table_that_does_not_exist'));

    $page = $this->get('/__database-error-probe')->assertStatus(500)->assertViewIs('errors.database');

    $page->assertSeeText('Something went wrong on our side and the action was not completed.')
        ->assertDontSee('SQLSTATE')->assertDontSee('a_table_that_does_not_exist')->assertDontSee(base_path(), false);
});
