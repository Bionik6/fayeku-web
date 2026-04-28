<?php

use App\Models\Shared\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated sme can logout and lands on the unified login', function () {
    $user = User::factory()->create(['profile_type' => 'sme']);

    $response = $this->actingAs($user)
        ->post(route('auth.logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('authenticated accountant can logout and lands on the unified login', function () {
    $user = User::factory()->accountantFirm()->create();

    $response = $this->actingAs($user)
        ->post(route('auth.logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('api logout revokes token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson(route('api.auth.logout'));

    $response->assertOk()
        ->assertJson(['message' => 'Déconnexion réussie.']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});
