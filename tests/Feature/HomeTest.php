<?php

use App\Models\User;

test('guests can view the homepage', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('Deploy Laravel to production');
    $response->assertSee('Start deploying');
    $response->assertSee('Login');
    $response->assertDontSee('Dashboard');
});

test('authenticated users can view the homepage', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertOk();
    $response->assertSee('Deploy Laravel to production');
    $response->assertSee('Go to dashboard');
    $response->assertSee('Dashboard');
});
