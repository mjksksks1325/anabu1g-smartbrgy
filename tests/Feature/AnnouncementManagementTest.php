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

it('lists announcements as read-only text with editing behind a closed flyout', function () {
    $announcement = Announcement::query()->create(['title' => 'Water interruption', 'body' => 'No water on Saturday.', 'status' => 'draft']);

    $page = announcementPage($this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('staff.announcements.index'))->assertOk());
    $flyout = '//ui-modal[@data-announcement-edit-dialog="'.$announcement->id.'"]';

    expect(trim($page->query('//*[@data-announcement-record]//*[@data-announcement-body]')->item(0)->textContent))->toBe('No water on Saturday.')
        ->and($page->query('//button[@data-announcement-edit-open="'.$announcement->id.'"]')->length)->toBe(1)
        ->and($page->query($flyout.'//dialog[@data-flux-flyout]')->length)->toBe(1)
        ->and($page->query($flyout.'[@data-open-on-load]')->length)->toBe(0)
        ->and($page->query($flyout.'//dialog//form//input[@name="title"]')->item(0)->getAttribute('value'))->toBe('Water interruption');
});

it('reopens the edited announcement with its error and keeps the other forms clean', function () {
    $admin = User::factory()->superAdmin()->create();
    $announcement = Announcement::query()->create(['title' => 'Water interruption', 'body' => 'No water on Saturday.', 'status' => 'draft']);
    $other = Announcement::query()->create(['title' => 'Clean-up drive', 'body' => 'Sunday morning.', 'status' => 'draft']);

    $page = announcementPage($this->actingAs($admin)
        ->from(route('staff.announcements.index'))
        ->followingRedirects()
        ->patch(route('staff.announcements.update', $announcement), ['_announcement' => $announcement->id, 'title' => '', 'body' => 'Updated body'])
        ->assertOk()
        ->assertSee('The title field is required.'));
    $open = $page->query('//ui-modal[@data-announcement-edit-dialog="'.$announcement->id.'"][@data-open-on-load]');
    $otherEdit = $page->query('//ui-modal[@data-announcement-edit-dialog="'.$other->id.'"]')->item(0);
    $create = $page->query('//ui-modal[@data-announcement-create-dialog]')->item(0);

    expect($open->length)->toBe(1)
        ->and($page->query('.//input[@name="title" and @aria-invalid="true"]', $open->item(0))->length)->toBe(1)
        ->and(trim($page->query('.//textarea[@name="body"]', $open->item(0))->item(0)->textContent))->toBe('Updated body')
        ->and($page->query('.//*[@aria-invalid="true"]', $otherEdit)->length)->toBe(0)
        ->and($page->query('.//*[@aria-invalid="true"]', $create)->length)->toBe(0)
        ->and($page->query('.//input[@name="title"]', $create)->item(0)->getAttribute('value'))->toBe('')
        ->and($create->hasAttribute('data-open-on-load'))->toBeFalse()
        ->and($announcement->fresh()->title)->toBe('Water interruption');
});

it('opens new announcements from a button and reopens the dialog with the typed values after an error', function () {
    $admin = User::factory()->superAdmin()->create();
    $existing = Announcement::query()->create(['title' => 'Water interruption', 'body' => 'No water on Saturday.', 'status' => 'draft']);
    $page = announcementPage($this->actingAs($admin)->get(route('staff.announcements.index'))->assertOk());

    expect($page->query('//*[@data-ui-page-header]//button[@data-announcement-create-open]')->length)->toBe(1)
        ->and($page->query('//ui-modal[@data-announcement-create-dialog][@data-open-on-load]')->length)->toBe(0);

    $page = announcementPage($this->from(route('staff.announcements.index'))->followingRedirects()
        ->post(route('staff.announcements.store'), ['title' => 'Power outage', 'body' => '', 'status' => 'draft'])->assertOk());
    $dialog = $page->query('//ui-modal[@data-announcement-create-dialog][@data-open-on-load]');
    $edit = $page->query('//ui-modal[@data-announcement-edit-dialog="'.$existing->id.'"]')->item(0);

    expect($dialog->length)->toBe(1)
        ->and($page->query('.//input[@name="title"]', $dialog->item(0))->item(0)->getAttribute('value'))->toBe('Power outage')
        ->and($page->query('.//textarea[@name="body" and @aria-invalid="true"]', $dialog->item(0))->length)->toBe(1)
        ->and($page->query('.//button[@data-announcement-create-close and @type="button"]', $dialog->item(0))->length)->toBe(1)
        ->and($page->query('.//*[@aria-invalid="true"]', $edit)->length)->toBe(0)
        ->and($edit->hasAttribute('data-open-on-load'))->toBeFalse();
    $this->assertDatabaseCount('announcements', 1);
});

it('does not offer the new announcement dialog to staff who cannot create announcements', function () {
    $viewer = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['announcements.view']]);
    Announcement::query()->create(['title' => 'Water interruption', 'body' => 'No water on Saturday.', 'status' => 'draft']);
    $page = announcementPage($this->actingAs($viewer)->get(route('staff.announcements.index'))->assertOk());

    expect($page->query('//button[@data-announcement-create-open]')->length)->toBe(0)
        ->and($page->query('//ui-modal[@data-announcement-create-dialog]')->length)->toBe(0)
        ->and($page->query('//button[@data-announcement-edit-open]')->length)->toBe(0)
        ->and($page->query('//ui-modal[@data-announcement-edit-dialog]')->length)->toBe(0)
        ->and($page->query('//button[@data-announcement-archive]')->length)->toBe(0)
        ->and($page->query('//*[@data-announcement-actions]//form')->length)->toBe(0)
        ->and(trim($page->query('//*[@data-announcement-body]')->item(0)->textContent))->toBe('No water on Saturday.');
});

it('counts every status in the filter cards and marks the selected one', function () {
    foreach (['published', 'published', 'draft', 'archived'] as $index => $status) {
        Announcement::query()->create(['title' => "Notice {$index}", 'body' => 'Details.', 'status' => $status, 'published_at' => $status === 'published' ? now() : null]);
    }

    $page = announcementPage($this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('staff.announcements.index', ['status' => 'draft']))->assertOk());
    $cards = collect($page->query('//nav[@data-announcement-filters]/a[@data-ui-kpi]'))
        ->mapWithKeys(fn (DOMElement $card): array => [
            $card->getAttribute('href') => [preg_replace('/\s+/', ' ', trim($card->textContent)), $card->getAttribute('aria-current')],
        ]);

    expect($cards->all())->toBe([
        route('staff.announcements.index', ['status' => 'all']) => ['All 4 Every record', ''],
        route('staff.announcements.index', ['status' => 'published']) => ['Published 2 On the resident portal', ''],
        route('staff.announcements.index', ['status' => 'draft']) => ['Drafts 1 Staff only', 'page'],
        route('staff.announcements.index', ['status' => 'archived']) => ['Archived 1 Kept for the record', ''],
    ])->and($page->query('//*[@data-announcement-record]')->length)->toBe(1);
});
