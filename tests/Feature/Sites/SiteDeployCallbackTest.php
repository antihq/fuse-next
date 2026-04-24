<?php

use App\Enums\ServerStatus;
use App\Enums\SiteStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('deploy callback sets site to deploying', function () {
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
        'status' => SiteStatus::Pending,
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'deploying']);

    $response->assertOk();
    $response->assertJson(['status' => 'deploying']);

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deploying);
});

test('deploy callback sets site to deployed', function () {
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
        'status' => SiteStatus::Deploying,
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'deployed']);

    $response->assertOk();
    $response->assertJson(['status' => 'deployed']);

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deployed);
});

test('deploy callback with error sets site to failed', function () {
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
        'status' => SiteStatus::Deploying,
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, ['error' => 'Git clone failed']);

    $response->assertStatus(400);
    $response->assertJson(['status' => 'failed']);

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Failed);
});

test('deploy callback cannot be called without signature', function () {
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
        'status' => SiteStatus::Pending,
    ]);

    $url = route('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url);

    $response->assertStatus(403);
});

test('deploy callback rejects expired signature', function () {
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
        'status' => SiteStatus::Pending,
    ]);

    $url = URL::temporarySignedRoute('sites.deploy-callback', now()->subMinutes(5), ['site' => $site]);

    $response = $this->post($url);

    $response->assertStatus(403);
});

test('deploy callback returns 400 for unknown status', function () {
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
        'status' => SiteStatus::Pending,
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'unknown']);

    $response->assertStatus(400);

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Pending);
});

test('deploy callback handles deploying status from pending', function () {
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
        'status' => SiteStatus::Pending,
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'deploying']);

    $response->assertOk();

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deploying);
});

test('deploy callback handles deploying status from failed', function () {
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
        'status' => SiteStatus::Failed,
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'deploying']);

    $response->assertOk();

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deploying);
});

test('deploy callback route bypasses csrf token requirement', function () {
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
        'status' => SiteStatus::Pending,
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'deploying']);

    $response->assertOk();
});

test('deploy callback saves mysql database name when provided', function () {
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
        'status' => SiteStatus::Deploying,
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, [
        'status' => 'deployed',
        'mysql_database' => 'site_42',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'deployed']);

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deployed);
    expect($site->mysql_database)->toBe('site_42');
});

test('deploy callback does not overwrite mysql database when not provided', function () {
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
        'status' => SiteStatus::Deploying,
        'mysql_database' => 'site_42',
    ]);

    $url = URL::signedRoute('sites.deploy-callback', ['site' => $site]);

    $response = $this->post($url, ['status' => 'deployed']);

    $response->assertOk();

    $site->refresh();
    expect($site->mysql_database)->toBe('site_42');
});
