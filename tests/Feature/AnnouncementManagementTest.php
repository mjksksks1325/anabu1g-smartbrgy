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
        ->and($page->query('//section[contains(@class, "announcement-create-panel")]//input[@name="title"]')->item(0)->getAttribute('value'))->toBe('')
        ->and($announcement->fresh()->title)->toBe('Water interruption');
});
