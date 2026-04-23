<?php

use App\Enums\ServerStatus;
use App\Enums\SiteStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('destroy callback deletes site on destroyed status', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->create([
        'server_id' => $server->id,
        'status' => SiteStatus::Deleting,
    ]);

    $url = URL::signedRoute('sites.destroy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'destroyed']);

    $response->assertOk();
    $response->assertJson(['status' => 'destroyed']);

    $this->assertDatabaseMissing('sites', ['id' => $site->id]);
});

test('destroy callback with error sets site to failed', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->create([
        'server_id' => $server->id,
        'status' => SiteStatus::Deleting,
    ]);

    $url = URL::signedRoute('sites.destroy-callback', ['site' => $site]);

    $response = $this->post($url, ['error' => 'Caddy reload failed']);

    $response->assertStatus(400);
    $response->assertJson(['status' => 'failed']);

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Failed);
});

test('destroy callback cannot be called without signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->create([
        'server_id' => $server->id,
        'status' => SiteStatus::Deleting,
    ]);

    $url = route('sites.destroy-callback', ['site' => $site]);

    $response = $this->post($url);

    $response->assertStatus(403);
});

test('destroy callback rejects expired signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->create([
        'server_id' => $server->id,
        'status' => SiteStatus::Deleting,
    ]);

    $url = URL::temporarySignedRoute('sites.destroy-callback', now()->subMinutes(5), ['site' => $site]);

    $response = $this->post($url);

    $response->assertStatus(403);
});

test('destroy callback returns 400 for unknown status', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->create([
        'server_id' => $server->id,
        'status' => SiteStatus::Deleting,
    ]);

    $url = URL::signedRoute('sites.destroy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'unknown']);

    $response->assertStatus(400);

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deleting);
});

test('destroy callback route bypasses csrf token requirement', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->create([
        'server_id' => $server->id,
        'status' => SiteStatus::Deleting,
    ]);

    $url = URL::signedRoute('sites.destroy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'destroyed']);

    $response->assertOk();
});

test('destroy callback does not delete site on unknown status', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->create([
        'server_id' => $server->id,
        'status' => SiteStatus::Deleting,
    ]);

    $url = URL::signedRoute('sites.destroy-callback', ['site' => $site]);

    $this->post($url, ['status' => 'unknown']);

    $this->assertDatabaseHas('sites', ['id' => $site->id]);
});
