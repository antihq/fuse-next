<?php

use App\Models\SshKey;
use App\Models\User;
use Livewire\Livewire;

test('user can add ssh key', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.create')
        ->set('name', 'MacBook Pro')
        ->set('public_key', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('ssh_keys', [
        'user_id' => $user->id,
        'name' => 'MacBook Pro',
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com',
    ]);
});

test('ssh key fingerprint is auto generated on create', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.create')
        ->set('name', 'MacBook Pro')
        ->set('public_key', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com')
        ->call('create');

    $key = SshKey::first();
    expect($key->fingerprint)->not->toBeNull();
    expect($key->fingerprint)->toMatch('/^[0-9A-F]{2}(:[0-9A-F]{2}){15}$/');
});

test('ssh key name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.create')
        ->set('name', '')
        ->set('public_key', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com')
        ->call('create')
        ->assertHasErrors(['name' => 'required']);
});

test('ssh key public key is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.create')
        ->set('name', 'MacBook Pro')
        ->set('public_key', '')
        ->call('create')
        ->assertHasErrors(['public_key' => 'required']);
});

test('ssh key public key must start with ssh-', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.create')
        ->set('name', 'MacBook Pro')
        ->set('public_key', 'not-a-valid-key')
        ->call('create')
        ->assertHasErrors(['public_key' => 'starts_with']);
});

test('user can delete their own ssh key', function () {
    $user = User::factory()->create();
    $key = SshKey::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.index')
        ->call('deleteKey', $key->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('ssh_keys', ['id' => $key->id]);
});

test('user cannot delete another users ssh key', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $key = SshKey::factory()->create(['user_id' => $otherUser->id]);

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.index')
        ->call('deleteKey', $key->id)
        ->assertForbidden();

    $this->assertDatabaseHas('ssh_keys', ['id' => $key->id]);
});

test('create page redirects to index after adding key', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.create')
        ->set('name', 'MacBook Pro')
        ->set('public_key', 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com')
        ->call('create')
        ->assertRedirect(route('ssh-keys.index'));
});

test('ssh keys index shows existing keys', function () {
    $user = User::factory()->create();
    SshKey::factory()->create(['user_id' => $user->id, 'name' => 'MacBook Pro']);

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.index')
        ->assertSee('MacBook Pro');
});

test('ssh keys index shows empty state when no keys', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.index')
        ->assertSee('No SSH keys yet.');
});

test('ssh keys index shows add ssh key button', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.index')
        ->assertSee('Add SSH Key');
});

test('fingerprint is invalid for malformed public key', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::ssh-keys.create')
        ->set('name', 'Bad Key')
        ->set('public_key', 'ssh-ed25519 not-valid-base64!!!')
        ->call('create')
        ->assertHasNoErrors();

    $key = SshKey::first();
    expect($key->fingerprint)->toBe('invalid');
});

test('pre-set fingerprint is not overwritten on create', function () {
    $key = SshKey::factory()->create([
        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com',
        'fingerprint' => 'CUSTOM:FINGERPRINT',
    ]);

    expect($key->fingerprint)->toBe('CUSTOM:FINGERPRINT');
});
