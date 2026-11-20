<?php

test('registration path redirects to login', function () {
    $response = $this->get(route('register'));

    $response->assertRedirect(route('login'));
});

test('registration store path redirects to login', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('login'));
});
