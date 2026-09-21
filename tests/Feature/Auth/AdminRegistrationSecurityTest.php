<?php

test('public registration is disabled for the staff administration system', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Unapproved User',
        'email' => 'unapproved@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertDatabaseCount('users', 0);
});
