<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function announcementPage(TestResponse $response): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

it('lists announcements as read-only text with editing behind a closed disclosure', function () {
    Announcement::query()->create(['title' => 'Water interruption', 'body' => 'No water on Saturday.', 'status' => 'draft']);

    $page = announcementPage($this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('staff.announcements.index'))->assertOk());

    expect(trim($page->query('//div[@class="announcement-record-body"]')->item(0)->textContent))->toBe('No water on Saturday.')
        ->and($page->query('//details[contains(@class, "announcement-edit")]')->length)->toBe(1)
        ->and($page->query('//details[contains(@class, "announcement-edit") and @open]')->length)->toBe(0)
        ->and(trim($page->query('//details[contains(@class, "announcement-edit")]/summary')->item(0)->textContent))->toBe('Edit announcement');
});

it('reopens the edited announcement with its error and keeps the create form empty', function () {
    $admin = User::factory()->superAdmin()->create();
    $announcement = Announcement::query()->create(['title' => 'Water interruption', 'body' => 'No water on Saturday.', 'status' => 'draft']);

    $page = announcementPage($this->actingAs($admin)
        ->from(route('staff.announcements.index'))
        ->followingRedirects()
        ->patch(route('staff.announcements.update', $announcement), ['_announcement' => $announcement->id, 'title' => '', 'body' => 'Updated body'])
        ->assertOk()
        ->assertSee('The title field is required.'));
    $open = $page->query('//details[contains(@class, "announcement-edit") and @open]');

    expect($open->length)->toBe(1)
        ->and($page->query('.//input[@name="title" and @aria-invalid="true"]', $open->item(0))->length)->toBe(1)
        ->and(trim($page->query('.//textarea[@name="body"]', $open->item(0))->item(0)->textContent))->toBe('Updated body')
        ->and($page->query('//dialog[@data-announcement-create-dialog]//input[@name="title"]')->item(0)->getAttribute('value'))->toBe('')
        ->and($page->query('//dialog[@data-announcement-create-dialog][@data-open-on-load]')->length)->toBe(0)
        ->and($announcement->fresh()->title)->toBe('Water interruption');
});

it('opens new announcements from a button and reopens the dialog with the typed values after an error', function () {
    $admin = User::factory()->superAdmin()->create();
    $page = announcementPage($this->actingAs($admin)->get(route('staff.announcements.index'))->assertOk());

    expect($page->query('//div[@class="page-heading"]//button[@data-announcement-create-open]')->length)->toBe(1)
        ->and($page->query('//dialog[@data-announcement-create-dialog][@data-open-on-load]')->length)->toBe(0)
        ->and($page->query('//section[contains(@class, "announcement-create-panel")]')->length)->toBe(0);

    $page = announcementPage($this->from(route('staff.announcements.index'))->followingRedirects()
        ->post(route('staff.announcements.store'), ['title' => 'Water interruption', 'body' => '', 'status' => 'draft'])->assertOk());
    $dialog = $page->query('//dialog[@data-announcement-create-dialog][@data-open-on-load]');

    expect($dialog->length)->toBe(1)
        ->and($page->query('.//input[@name="title"]', $dialog->item(0))->item(0)->getAttribute('value'))->toBe('Water interruption')
        ->and($page->query('.//textarea[@name="body" and @aria-invalid="true"]', $dialog->item(0))->length)->toBe(1)
        ->and($page->query('.//button[@data-announcement-create-close and @type="button"]', $dialog->item(0))->length)->toBe(2);
    $this->assertDatabaseCount('announcements', 0);
});

it('does not offer the new announcement dialog to staff who cannot create announcements', function () {
    $viewer = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['announcements.view']]);
    $page = announcementPage($this->actingAs($viewer)->get(route('staff.announcements.index'))->assertOk());

    expect($page->query('//button[@data-announcement-create-open]')->length)->toBe(0)
        ->and($page->query('//dialog[@data-announcement-create-dialog]')->length)->toBe(0);
});
