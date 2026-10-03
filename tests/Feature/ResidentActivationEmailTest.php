<?php

use App\Models\User;
use App\Notifications\ResidentPortalActivationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config()->set('mail.default', 'array');
    config()->set('mail.from.address', 'configured-sender@example.test');
    config()->set('mail.from.name', 'Laravel');
});

test('resident activation email renders dedicated barangay branding and safe registration links in both formats', function () {
    $residentNumber = 'ANB-000123';
    $activationCode = 'ABCD1234EFGH5678IJKL9012MNOP3456';

    Notification::route('mail', 'resident@example.test')
        ->notify(new ResidentPortalActivationNotification($residentNumber, $activationCode));

    $email = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

    expect($email)->toBeInstanceOf(Email::class)
        ->and($email->getSubject())->toBe('Barangay Anabu I-G Resident Portal Activation')
        ->and($email->getFrom()[0]->getName())->toBe('Barangay Anabu I-G')
        ->and($email->getFrom()[0]->getAddress())->toBe('configured-sender@example.test');

    foreach ([$email->getHtmlBody(), $email->getTextBody()] as $body) {
        expect($body)->toContain('Barangay Anabu I-G', 'Resident Portal', 'City of Imus, Cavite',
            'Continue your Resident Portal registration',
            'Your resident record was successfully matched',
            'Resident Number', $residentNumber, 'Activation Code', $activationCode,
            'Use this activation code to continue creating your Resident Portal account.',
            'This activation code expires in 24 hours.',
            'Please do not share your activation code with anyone.',
            'If you did not request this registration, please ignore this email or contact Barangay Anabu I-G.',
            'Continue Registration', route('portal.register'))
            ->not->toContain('Laravel', 'Regards, Laravel', 'laravel.com');
    }

    $document = new DOMDocument;
    @$document->loadHTML($email->getHtmlBody());
    $links = $document->getElementsByTagName('a');
    expect($links->length)->toBeGreaterThan(0);

    foreach ($links as $link) {
        expect($link->getAttribute('href'))->toBe(route('portal.register'))
            ->not->toContain($activationCode, $residentNumber, '?', '#');
    }

    expect($email->getHtmlBody())->toContain('src="cid:')
        ->and($email->getAttachments())->toHaveCount(1)
        ->and(config('mail.from.name'))->toBe('Laravel');
});

test('dedicated activation branding leaves password reset email branding unchanged', function () {
    $user = User::factory()->make(['email' => 'other@example.test']);
    $user->notify(new ResetPassword('test-reset-token'));

    $email = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

    expect($email->getFrom()[0]->getName())->toBe('Laravel')
        ->and($email->getHtmlBody())->toContain('Laravel')
        ->not->toContain('Continue your Resident Portal registration');
});
