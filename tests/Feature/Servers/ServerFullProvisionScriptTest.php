<?php

use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

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

test('full provision script works without any ssh keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertDontSee('Setup SSH keys for root');
    $response->assertDontSee('Enhance SSH security');
    $response->assertDontSee('PasswordAuthentication no');
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

test('full provision script embeds team members ssh keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $publicKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com';
    SshKey::factory()->create([
        'user_id' => $user->id,
        'public_key' => $publicKey,
    ]);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('Setup SSH keys for root');
    expect($content)->toContain($publicKey);
    expect($content)->toContain('Enhance SSH security');
    expect($content)->toContain('PasswordAuthentication no');
    expect($content)->toContain('cp /root/.ssh/authorized_keys /home/fuse/.ssh/authorized_keys');
});

test('full provision script includes ssh hardening only when team has keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('PasswordAuthentication no');
    expect($content)->not->toContain('service ssh restart');
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
    expect($content)->toContain('apt-get install -y valkey-server');
});

test('full provision script installs valkey from distro repos without external gpg keys', function () {
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
    expect($content)->toContain('apt-get install -y valkey-server');
    expect($content)->not->toContain('packages.valkey.io');
    expect($content)->not->toContain('valkey-gpg.key');
    expect($content)->not->toContain('valkey-archive-keyring');
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

test('full provision script includes known hosts for git providers when keys exist', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    SshKey::factory()->create(['user_id' => $user->id]);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('ssh-keyscan -H github.com');
    expect($content)->toContain('ssh-keyscan -H bitbucket.org');
    expect($content)->toContain('ssh-keyscan -H gitlab.com');
});

test('full provision script omits known hosts when no keys exist', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('ssh-keyscan');
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

test('full provision script configures caddy to import sites', function () {
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

    expect($content)->toContain('echo "Configure Caddy"');
    expect($content)->toContain('import /etc/caddy/sites.caddy');
    expect($content)->toContain('touch /etc/caddy/sites.caddy');
});

test('full provision script enables php fpm service', function () {
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

    expect($content)->toContain('systemctl enable php$version-fpm');
});

test('full provision script sets caddy directory ownership to fuse', function () {
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

    expect($content)->toContain('chown fuse:fuse /etc/caddy');
});

test('full provision script restarts php fpm services after creating fuse user', function () {
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

    expect($content)->toContain('echo "Start PHP-FPM services"');
    expect($content)->toContain('for version in 8.2 8.3 8.4 8.5');

    $fuseUserCreationPos = strpos($content, 'echo "Create fuse user"');
    $phpFpmRestartPos = strpos($content, 'echo "Start PHP-FPM services"');

    expect($fuseUserCreationPos)->not->toBeFalse();
    expect($phpFpmRestartPos)->not->toBeFalse();
    expect($phpFpmRestartPos)->toBeGreaterThan($fuseUserCreationPos);
});

test('full provision script includes keys from all team members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $ownerKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOwnerKey owner@example.com';
    $memberKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIMemberKey member@example.com';

    SshKey::factory()->create(['user_id' => $owner->id, 'public_key' => $ownerKey]);
    SshKey::factory()->create(['user_id' => $member->id, 'public_key' => $memberKey]);

    $server = Server::factory()->create(['team_id' => $team->id]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain($ownerKey);
    expect($content)->toContain($memberKey);
    expect($content)->toContain('Setup SSH keys for root');
    expect($content)->toContain('Enhance SSH security');
});

test('full provision script embeds all keys when one user has multiple ssh keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $key1 = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKeyOne user@example.com';
    $key2 = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKeyTwo user@example.com';

    SshKey::factory()->create(['user_id' => $user->id, 'public_key' => $key1, 'name' => 'Laptop']);
    SshKey::factory()->create(['user_id' => $user->id, 'public_key' => $key2, 'name' => 'Desktop']);

    $server = Server::factory()->create(['team_id' => $team->id]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain($key1);
    expect($content)->toContain($key2);
    expect($content)->toContain('Setup SSH keys for root');
    expect($content)->toContain('Enhance SSH security');
});

test('full provision script embeds keys when only some team members have keys', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $ownerKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOwnerKey owner@example.com';
    SshKey::factory()->create(['user_id' => $owner->id, 'public_key' => $ownerKey]);

    $server = Server::factory()->create(['team_id' => $team->id]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain($ownerKey);
    expect($content)->toContain('Setup SSH keys for root');
    expect($content)->toContain('Enhance SSH security');
});

test('full provision script does not include keys from users not on the team', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $ownerKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOwnerKey owner@example.com';
    $otherKey = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOtherKey other@example.com';

    SshKey::factory()->create(['user_id' => $owner->id, 'public_key' => $ownerKey]);
    SshKey::factory()->create(['user_id' => $otherUser->id, 'public_key' => $otherKey]);

    $server = Server::factory()->create(['team_id' => $team->id]);

    $url = URL::signedRoute('servers.full-provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain($ownerKey);
    expect($content)->not->toContain($otherKey);
});
