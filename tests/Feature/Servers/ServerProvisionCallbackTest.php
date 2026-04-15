<?php

use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Jobs\TestServerConnectivity;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['services.server_ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com']);
    Queue::fake();
});

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

    Queue::assertPushed(TestServerConnectivity::class, fn ($job) => $job->server->id === $server->id);
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

    Queue::assertNothingPushed();
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

test('callback on failed server triggers re-provisioning', function () {
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

    Queue::assertPushed(TestServerConnectivity::class, fn ($job) => $job->server->id === $server->id);
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

    Queue::assertNothingPushed();
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

    Queue::assertNothingPushed();
});

test('provision callback from connected status does not dispatch test connectivity job', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Connected,
    ]);

    $url = URL::signedRoute('servers.provision-callback', ['server' => $server]);

    $response = $this->post($url);

    $response->assertOk();
    $response->assertJson(['status' => 'provisioning']);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioning);

    Queue::assertNothingPushed();
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
