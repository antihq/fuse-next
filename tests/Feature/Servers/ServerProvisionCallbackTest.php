<?php

use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('provision callback can be called with signed url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url);

    $response->assertOk();
    $response->assertJson(['status' => 'provisioning']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioning);
});

test('provision callback returns already_provisioned if server is already provisioned', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url);

    $response->assertOk();
    $response->assertJson(['status' => 'already_provisioned']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioned);
});

test('provision callback cannot be called without signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $url = route('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url);

    $response->assertStatus(403);
});

test('provision callback rejects expired signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $url = URL::temporarySignedRoute('servers.provision-callback', now()->subMinutes(5), ['server' => $server]);

    $response = $this->post($url);

    $response->assertStatus(403);
});

test('callback on failed server sets status to provisioning', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Failed,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url);

    $response->assertOk();
    $response->assertJson(['status' => 'provisioning']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioning);
});

test('provision callback with completed status sets server to provisioned', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioning,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url, ['status' => 'completed']);

    $response->assertOk();
    $response->assertJson(['status' => 'provisioned']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioned);
});

test('provision callback with error param sets server to failed', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioning,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url, ['error' => 'Installation failed']);

    $response->assertOk();
    $response->assertJson(['status' => 'failed']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Failed);
});

test('provision callback with error from pending status sets server to failed', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url, ['error' => 'Script failed']);

    $response->assertOk();
    $response->assertJson(['status' => 'failed']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Failed);
});

test('provision callback with completed from pending status sets server to provisioned', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url, ['status' => 'completed']);

    $response->assertOk();
    $response->assertJson(['status' => 'provisioned']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioned);
});

test('provision callback route bypasses csrf token requirement', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url);

    $response->assertOk();
});

test('provision callback prioritizes error over completed status', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioning,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url, ['error' => 'Something broke', 'status' => 'completed']);

    $response->assertOk();
    $response->assertJson(['status' => 'failed']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Failed);
});

test('provision callback ignores error when server is already provisioned', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url, ['error' => 'Late error']);

    $response->assertOk();
    $response->assertJson(['status' => 'already_provisioned']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioned);
});

test('provision callback on provisioning server is idempotent', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioning,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url);

    $response->assertOk();
    $response->assertJson(['status' => 'provisioning']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioning);
});
