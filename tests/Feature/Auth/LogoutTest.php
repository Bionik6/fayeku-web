<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Shared\User;

uses(RefreshDatabase::class);

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('auth.logout'));

    $response->assertRedirect(route('home'));
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
