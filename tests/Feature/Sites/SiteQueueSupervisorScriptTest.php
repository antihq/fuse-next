<?php

use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('queue supervisor setup script can be retrieved with signed url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->queueEnabled()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
    $response->assertSee('#!/bin/bash');
    $response->assertSee('set -euo pipefail');
});

test('queue supervisor script cannot be accessed without signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->queueEnabled()->create([
        'server_id' => $server->id,
    ]);

    $url = route('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('queue supervisor script rejects expired signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->queueEnabled()->create([
        'server_id' => $server->id,
    ]);

    $url = URL::temporarySignedRoute('sites.queue-supervisor-script', now()->subMinutes(5), ['site' => $site]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('queue supervisor setup script includes supervisor config', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->queueEnabled()->create([
        'server_id' => $server->id,
        'domain' => 'myapp.com',
        'php_version' => '8.4',
    ]);

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('[program:myapp.com-worker]');
    expect($content)->toContain('command=/usr/bin/php8.4 /home/fuse/myapp.com/artisan queue:work database --sleep=3 --tries=3 --max-time=3600');
    expect($content)->toContain('stdout_logfile=/home/fuse/myapp.com/storage/logs/worker.log');
    expect($content)->toContain('user=fuse');
    expect($content)->toContain('autostart=true');
    expect($content)->toContain('autorestart=true');
});

test('queue supervisor setup script includes supervisorctl commands', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->queueEnabled()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('sudo supervisorctl reread');
    expect($content)->toContain('sudo supervisorctl update');
    expect($content)->toContain('sudo supervisorctl start example.com-worker:*');
});

test('queue supervisor setup script ensures log directory', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->queueEnabled()->create([
        'server_id' => $server->id,
    ]);

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Ensure log directory exists"');
    expect($content)->toContain('mkdir -p "$DEPLOY_DIR/storage/logs"');
    expect($content)->toContain('chown -R fuse:fuse "$DEPLOY_DIR/storage/logs"');
});

test('queue supervisor setup script writes config to correct path', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->queueEnabled()->create([
        'server_id' => $server->id,
        'domain' => 'myapp.com',
    ]);

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('SUPERVISOR_CONF="/etc/supervisor/conf.d/${DOMAIN}-worker.conf"');
});

test('queue supervisor setup script uses sudo tee to write config', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->queueEnabled()->create([
        'server_id' => $server->id,
    ]);

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('sudo tee "$SUPERVISOR_CONF" > /dev/null');
    expect($content)->not->toContain('cat > "$SUPERVISOR_CONF"');
});

test('queue supervisor teardown script is returned when queue disabled', function () {
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

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "=== Removing queue supervisor for $DOMAIN ==="');
    expect($content)->toContain('echo "Stop queue worker"');
    expect($content)->toContain('sudo supervisorctl stop myapp.com-worker:*');
});

test('queue supervisor teardown script removes config file', function () {
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

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Remove supervisor configuration"');
    expect($content)->toContain('sudo rm "$SUPERVISOR_CONF"');
    expect($content)->toContain('sudo supervisorctl reread');
    expect($content)->toContain('sudo supervisorctl update');
});

test('queue supervisor teardown script handles missing config gracefully', function () {
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

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "No configuration found, skipping"');
});

test('queue supervisor teardown script does not include setup operations', function () {
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

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('[program:');
    expect($content)->not->toContain('queue:work');
    expect($content)->not->toContain('supervisorctl start');
    expect($content)->not->toContain('mkdir -p');
});

test('default site without queue enabled returns teardown script', function () {
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

    expect($site->queue_enabled)->toBeFalse();

    $url = URL::signedRoute('sites.queue-supervisor-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('Removing queue supervisor');
    expect($content)->not->toContain('Setting up queue supervisor');
});
