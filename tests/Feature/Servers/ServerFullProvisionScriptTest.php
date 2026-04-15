<?php

use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['services.server_ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com']);
});

test('full provision script can be retrieved with signed url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
    $response->assertSee('#!/bin/bash');
    $response->assertSee('set -euo pipefail');
    $response->assertSee('=== Starting server provisioning ===');
});

test('full provision script returns 500 if ssh public key is not configured', function () {
    config(['services.server_ssh_public_key' => null]);

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertStatus(500);
});

test('full provision script rejects empty string ssh public key', function () {
    config(['services.server_ssh_public_key' => '']);

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertStatus(500);
});

test('full provision script cannot be accessed without signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = route('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('full provision script rejects expired signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::temporarySignedRoute('servers.full-provision-script', now()->subMinutes(5), ['server' => $server]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('full provision script includes caddy installation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Install Caddy 2 webserver"');
    expect($content)->toContain('dl.cloudsmith.io/public/caddy/stable/gpg.key');
    expect($content)->toContain('apt-get install -y caddy=2.*');
});

test('full provision script includes mysql installation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Install MySQL 8.0"');
    expect($content)->toContain('apt-get install -y mysql-server');
    expect($content)->toContain('CREATE DATABASE fuse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
});

test('full provision script includes valkey installation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Install Valkey (Redis compatible)"');
    expect($content)->toContain('https://packages.valkey.io/gpg');
    expect($content)->toContain('apt-get install -y valkey-server');
});

test('full provision script includes php installation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Install PHP 8.2, 8.3, 8.4, 8.5"');
    expect($content)->toContain('ppa:ondrej/php');
    expect($content)->toContain('for version in 8.2 8.3 8.4 8.5');
});

test('full provision script includes composer installation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Install Composer 2"');
    expect($content)->toContain('getcomposer.org/installer');
    expect($content)->toContain('/usr/local/bin/composer');
});

test('full provision script includes nodejs installation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Install Node.js 22 LTS"');
    expect($content)->toContain('deb.nodesource.com/setup_22.x');
});

test('full provision script includes fuse user creation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Create fuse user"');
    expect($content)->toContain('useradd -m fuse');
    expect($content)->toContain('/home/fuse/default');
});

test('full provision script includes ssh key and security hardening', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Enhance SSH security"');
    expect($content)->toContain('PasswordAuthentication no');
    expect($content)->toContain('ssh-keyscan -H github.com');
});

test('full provision script includes callback url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $callbackUrl = URL::signedRoute('servers.provision-callback', ['server' => $server]);
    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "=== Provisioning complete ==="');
    expect($content)->toContain('curl -s -X POST');
    expect($content)->toContain($callbackUrl);
});

test('full provision script generates random mysql password', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('MYSQL_PASSWORD=');

    $passwordLine = null;
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (str_starts_with($line, "MYSQL_PASSWORD='")) {
            $passwordLine = $line;
            break;
        }
    }

    expect($passwordLine)->not->toBeNull();

    $password = substr($passwordLine, strlen("MYSQL_PASSWORD='"), -1);

    expect(strlen($password))->toBe(32);
});

test('full provision script configures firewall with force flag', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Configure firewall (UFW)"');
    expect($content)->toContain('ufw --force enable');
    expect($content)->not->toContain('yes | ufw enable');
});

test('full provision script uses force flags on apt upgrade', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Upgrade packages"');
    expect($content)->toContain('apt-get upgrade -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y');
});

test('full provision script avoids shell pipes for external downloads', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('| gpg --dearmor');
    expect($content)->not->toContain('| tee /etc/apt/sources.list.d');
    expect($content)->not->toContain('| tee -a');
    expect($content)->not->toContain('| php -- --2');
    expect($content)->not->toContain('| bash -');
    expect($content)->toContain('-o /tmp/caddy-gpg.key');
    expect($content)->toContain('-o /tmp/valkey-gpg.key');
    expect($content)->toContain('-o /tmp/composer-installer');
    expect($content)->toContain('-o /tmp/nodesource-setup.sh');
});

test('full provision script connects to mysql without password for initial root user setup', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('service mysql start');
    expect($content)->toContain('mysql --user="root" -e "ALTER USER \'root\'@\'localhost\' IDENTIFIED BY');
    expect($content)->not->toContain('mysql --user="root" --password="$MYSQL_PASSWORD" -e "ALTER USER \'root\'@\'localhost\'');
});

test('full provision script includes error handling', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('REPORT_URL=');
    expect($content)->toContain('reportError() {');
    expect($content)->toContain('trap \'reportError "Script failed at line');
    expect($content)->toContain('ERR');
});
