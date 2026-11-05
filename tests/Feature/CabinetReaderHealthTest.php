<?php

use App\Models\CabinetDevice;
use App\Models\User;

it('renders separate drawer status only when the Pi reports that drawer', function (array $health, array $statuses, string $summary) {
    $this->freezeTime();
    $cabinet = CabinetDevice::factory()->create(['component_health' => $health, 'last_seen_at' => now(), 'reported_status' => 'online']);

    $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.smart-cabinet.index'))->assertOk()->assertSee($summary);
    expect(array_column($cabinet->folderReaderHealthItems(), 'status'))->toBe($statuses);
    if ($statuses === ['Not reported', 'Not reported']) {
        $html = $response->getContent();
        $start = strpos($html, 'id="cabinet-readers-title"');
        $panel = substr($html, $start, strpos($html, '</section>', $start) - $start);
        expect($panel)->not->toContain('>Disconnected</span>')->toContain('Folder RFID is connected overall.');
    }
})->with([
    'overall status only' => [['folder_rfid' => 'connected'], ['Not reported', 'Not reported'], 'Per-drawer status not reported'],
    'one drawer reported' => [['folder_rfid' => 'connected', 'folder_rfid_drawer_1' => 'connected'], ['Connected', 'Not reported'], 'Per-drawer status not reported'],
    'one disconnected drawer' => [['folder_rfid_drawer_1' => 'connected', 'folder_rfid_drawer_2' => 'disconnected'], ['Connected', 'Disconnected'], 'Reader needs attention'],
    'both drawers reported' => [['folder_rfid_drawer_1' => 'connected', 'folder_rfid_drawer_2' => 'connected'], ['Connected', 'Connected'], 'Both readers connected'],
]);

it('does not present stale drawer connection reports as current', function () {
    $this->freezeTime();
    $cabinet = CabinetDevice::factory()->create(['component_health' => ['folder_rfid_drawer_1' => 'connected', 'folder_rfid_drawer_2' => 'connected'], 'last_seen_at' => now()->subSeconds(91), 'reported_status' => 'online']);

    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.smart-cabinet.index'))->assertOk()->assertSee('Reader status not current')->assertDontSee('Both readers connected');
    expect(array_column($cabinet->folderReaderHealthItems(), 'status'))->toBe(['Not current', 'Not current']);
});

it('identifies unsaved reader mappings and suggests aliases matching the Pi handoff', function () {
    $cabinet = CabinetDevice::factory()->create(['component_health' => ['folder_rfid' => 'connected'], 'last_seen_at' => now(), 'reported_status' => 'online']);
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.smart-cabinet.index'))->assertOk()
        ->assertSee('Reader mapping has not been saved')->assertSee('Prefilled values are suggestions.')
        ->assertSee('value="reader-one"', false)->assertSee('value="reader-two"', false);
    $this->assertDatabaseCount('cabinet_readers', 0);

    $this->patch(route('admin.smart-cabinet.readers.update', $cabinet), ['drawer_1_reader_identifier' => 'reader-one', 'drawer_2_reader_identifier' => 'reader-two'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('cabinet_readers', ['cabinet_device_id' => $cabinet->id, 'reader_identifier' => 'reader-one', 'drawer_reference' => 'drawer_1']);
    $this->assertDatabaseHas('cabinet_readers', ['cabinet_device_id' => $cabinet->id, 'reader_identifier' => 'reader-two', 'drawer_reference' => 'drawer_2']);
    $this->get(route('admin.smart-cabinet.index'))->assertOk()->assertDontSee('Reader mapping has not been saved')->assertSee('Per-drawer status not reported');
});

it('does not describe stale overall RFID health as a current connection', function () {
    $this->freezeTime();
    CabinetDevice::factory()->create(['component_health' => ['folder_rfid' => 'connected'], 'last_seen_at' => now()->subSeconds(91), 'reported_status' => 'online']);
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.smart-cabinet.index'))->assertOk()
        ->assertSee('Per-drawer status not reported')->assertDontSee('Folder RFID is connected overall.');
});
