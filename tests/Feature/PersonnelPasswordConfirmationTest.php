<?php

use App\Models\User;

it('confirms personnel passwords through Fortify before opening security', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $this->getJson(route('password.confirmation'))->assertJsonPath('confirmed', false);
    $this->getJson(route('security.edit'))->assertStatus(423);
    $this->postJson(route('staff.password.confirm'), ['password' => 'password'])
        ->assertCreated()->assertSessionHas('auth.password_confirmed_at');
    $this->getJson(route('password.confirmation'))->assertJsonPath('confirmed', true);
    $this->get(route('security.edit'))->assertOk();
})->with(['staff', 'admin']);

it('rejects incorrect or missing passwords without unlocking security', function (?string $password) {
    $this->actingAs(User::factory()->create());
    $this->postJson(route('staff.password.confirm'), ['password' => $password])
        ->assertUnprocessable()->assertJsonValidationErrors('password')
        ->assertSessionMissing('auth.password_confirmed_at');
    $this->getJson(route('security.edit'))->assertStatus(423);
})->with(['wrong', null]);

it('expires confirmed passwords using the existing server timeout', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => now()->subSeconds(config('auth.password_timeout') + 1)->timestamp]);
    $this->getJson(route('password.confirmation'))->assertJsonPath('confirmed', false);
    $this->getJson(route('security.edit'))->assertStatus(423);
});

it('throttles repeated personnel confirmation attempts', function () {
    $this->actingAs(User::factory()->create());
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->postJson(route('staff.password.confirm'), ['password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson(route('staff.password.confirm'), ['password' => 'password'])->assertTooManyRequests()
        ->assertSessionMissing('auth.password_confirmed_at');
});

it('keeps guest and resident accounts out of personnel confirmation', function () {
    $this->postJson(route('staff.password.confirm'), ['password' => 'password'])->assertUnauthorized();
    $this->actingAs(User::factory()->resident()->create())
        ->postJson(route('staff.password.confirm'), ['password' => 'password'])->assertForbidden();
});

it('retains full page confirmation for direct security requests', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    $this->get(route('password.confirm'))->assertOk()->assertSee('confirm-password-button');
    $this->post(route('password.confirm.store'), ['password' => 'password'])->assertRedirect(route('security.edit'));
});

it('includes the accessible confirmation popup across personnel layouts', function (string $route) {
    $this->actingAs(User::factory()->superAdmin()->create())->get(route($route))
        ->assertOk()->assertSee('id="personnel-password-confirmation"', false)
        ->assertSee('aria-labelledby="personnel-confirm-title"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('data-status-url="'.route('password.confirmation').'"', false)
        ->assertSee('action="'.route('staff.password.confirm').'"', false);
})->with(['staff.dashboard', 'staff.rfid-files.index', 'profile.edit']);
