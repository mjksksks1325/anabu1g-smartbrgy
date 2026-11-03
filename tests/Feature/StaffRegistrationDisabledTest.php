<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

it('keeps open staff self-registration disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse()
        ->and(Route::has('register'))->toBeFalse()
        ->and(Route::has('register.store'))->toBeFalse();

    $this->post('/register', [
        'name' => 'Walk-up Visitor',
        'email' => 'visitor@example.test',
        'password' => 'password-123456',
        'password_confirmation' => 'password-123456',
    ])->assertStatus(404);

    expect(User::query()->where('email', 'visitor@example.test')->exists())->toBeFalse();
    $this->assertGuest();
});
