<?php

use App\Livewire\Settings\Profile;
use App\Models\ResidentRegistrationApplication;
use App\Models\User;
use App\Notifications\ResidentRegistrationReviewedNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Account emails are never reported as sent when the server does not deliver email (MAIL_MAILER=log writes them,
 * with their links, to the application log). WG9 covered password reset and WG10 resident email confirmation;
 * these cover the staff email verification link and the new-resident application result.
 */

const MAIL_NOT_SENT = 'The verification email was not sent: this server does not send email yet, or the mail server refused it. Ask the system administrator to check the email settings.';

test('a staff verification link is not sent, or claimed, when the server does not deliver email', function () {
    Notification::fake();
    config(['mail.default' => 'log']);
    $staff = User::factory()->assignedOperations()->create(['email_verified_at' => null]);

    $this->actingAs($staff)->from(route('verification.notice'))->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHasErrors(['email' => MAIL_NOT_SENT])
        ->assertSessionMissing('status');
    Livewire::actingAs($staff)->test(Profile::class)->call('resendVerificationNotification');

    Notification::assertNothingSent();
});

test('a staff verification link is still sent by a server that delivers email', function () {
    Notification::fake();
    $staff = User::factory()->assignedOperations()->create(['email_verified_at' => null]);

    $this->actingAs($staff)->from(route('verification.notice'))->post(route('verification.send'))
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($staff, VerifyEmail::class);
});

test('an application review is saved but its result is not queued, or claimed, when the server does not deliver email', function () {
    Storage::fake('local');
    Notification::fake();
    config(['mail.default' => 'log']);
    $application = ResidentRegistrationApplication::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('staff.resident-applications.update', $application), ['decision' => 'needs_correction', 'remarks' => 'Upload a clearer photo.'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Registration review saved. This server does not send email yet, so the applicant was not emailed: tell them the result when they contact the barangay.');

    expect($application->fresh()->status)->toBe('needs_correction');
    Notification::assertNothingSent();
    expect(DB::table('administrative_audits')->where('action', 'admin.resident-applications.result-mail-unavailable')->where('record', $application->reference)->count())->toBe(1);
});

test('an application review result is queued for the applicant by a server that delivers email', function () {
    Storage::fake('local');
    Notification::fake();
    $application = ResidentRegistrationApplication::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('staff.resident-applications.update', $application), ['decision' => 'declined', 'remarks' => 'Not a resident of the barangay.'])
        ->assertSessionHas('status', 'Registration review saved. The result email has been queued for the applicant.');

    Notification::assertSentTo(new AnonymousNotifiable, ResidentRegistrationReviewedNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $application->email);
    expect(DB::table('administrative_audits')->where('action', 'admin.resident-applications.result-mail-unavailable')->count())->toBe(0);
});
