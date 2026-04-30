<?php

use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('destroy script can be retrieved with signed url', function () {
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
        'domain' => 'example.com',
    ]);

    $url = URL::signedRoute('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
    $response->assertSee('#!/bin/bash');
    $response->assertSee('set -euo pipefail');
    $response->assertSee('Starting site removal');
});

test('destroy script cannot be accessed without signature', function () {
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
    ]);

    $url = route('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('destroy script rejects expired signature', function () {
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
    ]);

    $url = URL::temporarySignedRoute('sites.destroy-script', now()->subMinutes(5), ['site' => $site]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('destroy script includes site domain', function () {
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
        'domain' => 'myapp.com',
    ]);

    $url = URL::signedRoute('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain("DOMAIN='myapp.com'");
    expect($content)->toContain('DEPLOY_DIR="/home/fuse/$DOMAIN"');
});

test('destroy script includes destroy callback url', function () {
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
    ]);

    $callbackUrl = URL::signedRoute('sites.destroy-callback', ['site' => $site]);
    $url = URL::signedRoute('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "=== Site removal completed successfully ==="');
    expect($content)->toContain('{"status":"destroyed"}');
    expect($content)->toContain($callbackUrl);
});

test('destroy script includes error handling', function () {
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
    ]);

    $url = URL::signedRoute('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('reportError()');
    expect($content)->toContain('Site removal failed');
    expect($content)->toContain('trap \'reportError "Script failed at line $LINENO"\' ERR');
});

test('destroy script removes caddy config', function () {
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
        'domain' => 'example.com',
    ]);

    $url = URL::signedRoute('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Remove Caddy configuration for $DOMAIN"');
    expect($content)->toContain('CADDY_CONFIG="/etc/caddy/sites.caddy"');
    expect($content)->toContain("sed -i '/^example.com {/,/^}/d'");
    expect($content)->toContain('echo "Removed Caddy config block"');
});

test('destroy script removes deploy directory', function () {
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
    ]);

    $url = URL::signedRoute('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Remove deploy directory $DEPLOY_DIR"');
    expect($content)->toContain('rm -rf "$DEPLOY_DIR"');
    expect($content)->toContain('echo "Directory removed"');
});

test('destroy script reloads caddy and restarts php fpm', function () {
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
    ]);

    $url = URL::signedRoute('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Reload Caddy"');
    expect($content)->toContain('sudo service caddy reload');
    expect($content)->toContain('echo "Reload PHP-FPM"');
    expect($content)->toContain('sudo service php8.5-fpm reload');
    expect($content)->toContain('flock -w 10 9');
});

test('destroy script does not include deploy operations', function () {
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
    ]);

    $url = URL::signedRoute('sites.destroy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('git clone');
    expect($content)->not->toContain('composer install');
    expect($content)->not->toContain('npm install');
    expect($content)->not->toContain('php artisan key:generate');
    expect($content)->not->toContain('php artisan migrate');
    expect($content)->not->toContain('cat >> "$CADDY_CONFIG"');
});
