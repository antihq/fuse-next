<?php

use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['services.server_ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAb5M7vlstlBOPx6NocXAewxzfxX8AujDifR0lrQf+On fuse@example.com']);
});

test('provision script can be retrieved with signed url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
    $response->assertSee('set -euo pipefail');
    $response->assertSee('Setup SSH keys for root');
    $response->assertSee('mkdir -p /root/.ssh');
    $response->assertSee('chmod 700 /root/.ssh');
    $response->assertSee('chmod 600 /root/.ssh/authorized_keys');
    $response->assertSee(config('services.server_ssh_public_key'));
});

test('provision script returns 500 if ssh public key is not configured', function () {
    config(['services.server_ssh_public_key' => null]);

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertStatus(500);
});

test('provision script rejects empty string ssh public key', function () {
    config(['services.server_ssh_public_key' => '']);

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertStatus(500);
});

test('provision script cannot be accessed without signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = route('servers.provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('provision script rejects expired signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::temporarySignedRoute('servers.provision-script', now()->subMinutes(5), ['server' => $server]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('provision script embeds public key in heredoc', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();
    $publicKey = config('services.server_ssh_public_key');

    expect($content)->toContain('cat <<EOF >> /root/.ssh/authorized_keys');
    expect($content)->toContain($publicKey);
    expect($content)->toContain('EOF');
});

test('provision script includes callback url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $callbackUrl = URL::signedRoute('servers.provision-callback', ['server' => $server]);
    $url = URL::signedRoute('servers.provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Notifying Fuse..."');
    expect($content)->toContain('curl -s -X POST');
    expect($content)->toContain($callbackUrl);
});

test('provision script includes error handling', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $url = URL::signedRoute('servers.provision-script', ['server' => $server]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('REPORT_URL=');
    expect($content)->toContain('reportError() {');
    expect($content)->toContain('trap \'reportError "Script failed at line');
    expect($content)->toContain('ERR');
});
