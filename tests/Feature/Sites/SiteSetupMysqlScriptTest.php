<?php

use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('setup mysql script can be retrieved with signed url', function () {
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
        'status' => 'deployed',
    ]);

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
    $response->assertSee('#!/bin/bash');
    $response->assertSee('set -euo pipefail');
    $response->assertSee('Starting MySQL setup');
});

test('setup mysql script cannot be accessed without signature', function () {
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

    $url = route('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('setup mysql script rejects expired signature', function () {
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

    $url = URL::temporarySignedRoute('sites.setup-mysql-script', now()->subMinutes(5), ['site' => $site]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('setup mysql script checks for my cnf existence', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('/home/fuse/.my.cnf');
    expect($content)->toContain('MySQL not available on this server');
});

test('setup mysql script creates database named after site id', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('CREATE DATABASE IF NOT EXISTS $DB_NAME');
    expect($content)->toContain('CREATE USER \'$DB_USER\'@\'127.0.0.1\'');
    expect($content)->toContain('GRANT ALL PRIVILEGES ON $DB_NAME.*');
});

test('setup mysql script generates random password for site database', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('openssl rand -hex 16');
});

test('setup mysql script updates env with mysql credentials', function () {
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
        'domain' => 'mysite.com',
    ]);

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('DB_CONNECTION=mysql');
    expect($content)->toContain('DB_HOST=127.0.0.1');
    expect($content)->toContain('DB_PORT=3306');
    expect($content)->toContain('DB_DATABASE=$DB_NAME');
    expect($content)->toContain('DB_USERNAME=$DB_USER');
    expect($content)->toContain('DB_PASSWORD=$DB_PASSWORD');
});

test('setup mysql script removes sqlite references from env', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('/^DB_SQLITE/d');
    expect($content)->toContain('rm -f "$DEPLOY_DIR/database/database.sqlite"');
});

test('setup mysql script runs migrations against mysql', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('php artisan migrate --force');
    expect($content)->toContain('php artisan config:cache');
});

test('setup mysql script includes error handling', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('reportError() {');
    expect($content)->toContain('trap \'reportError "Script failed at line $LINENO"\' ERR');
});

test('setup mysql script sends mysql database name in callback', function () {
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

    $callbackUrl = URL::signedRoute('sites.deploy-callback', ['site' => $site]);
    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('"mysql_database":"$DB_NAME"');
    expect($content)->toContain($callbackUrl);
});

test('setup mysql script creates database with utf8mb4', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
});

test('setup mysql script creates database before updating env', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    $createDbPos = strpos($content, 'CREATE DATABASE IF NOT EXISTS');
    $updateEnvPos = strpos($content, 'Update .env with MySQL credentials');

    expect($createDbPos)->not->toBeFalse();
    expect($updateEnvPos)->not->toBeFalse();
    expect($updateEnvPos)->toBeGreaterThan($createDbPos);
});

test('setup mysql script updates env before running migrations', function () {
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

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    $updateEnvPos = strpos($content, 'Update .env with MySQL credentials');
    $migratePos = strpos($content, 'php artisan migrate --force');

    expect($updateEnvPos)->not->toBeFalse();
    expect($migratePos)->not->toBeFalse();
    expect($migratePos)->toBeGreaterThan($updateEnvPos);
});

test('setup mysql script uses deploy directory based on site domain', function () {
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
        'domain' => 'myapp.example.com',
    ]);

    $url = URL::signedRoute('sites.setup-mysql-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain("DOMAIN='myapp.example.com'");
    expect($content)->toContain('DEPLOY_DIR="/home/fuse/$DOMAIN"');
});
