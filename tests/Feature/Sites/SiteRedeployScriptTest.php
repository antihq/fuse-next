<?php

use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\URL;

test('redeploy script can be retrieved with signed url', function () {
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
        'repository' => 'https://github.com/user/repo.git',
    ]);

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/x-shellscript; charset=utf-8');
    $response->assertSee('#!/bin/bash');
    $response->assertSee('set -euo pipefail');
    $response->assertSee('Starting site redeployment');
});

test('redeploy script cannot be accessed without signature', function () {
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

    $url = route('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('redeploy script rejects expired signature', function () {
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

    $url = URL::temporarySignedRoute('sites.redeploy-script', now()->subMinutes(5), ['site' => $site]);

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('redeploy script includes site domain and repository', function () {
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
        'repository' => 'https://github.com/acme/myapp.git',
    ]);

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain("DOMAIN='myapp.com'");
    expect($content)->toContain("REPOSITORY='https://github.com/acme/myapp.git'");
});

test('redeploy script uses git pull instead of clone', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Pulling latest changes from repository"');
    expect($content)->toContain('cd "$DEPLOY_DIR"');
    expect($content)->toContain('git pull "$REPOSITORY"');
    expect($content)->not->toContain('git clone');
    expect($content)->not->toContain('rm -rf');
});

test('redeploy script includes composer install', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Install Composer dependencies"');
    expect($content)->toContain('$PHP /usr/local/bin/composer install --optimize-autoloader --no-dev --no-interaction');
});

test('redeploy script does not include caddy config', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('echo "Generate Caddy configuration"');
    expect($content)->not->toContain('CADDY_CONFIG');
    expect($content)->not->toContain('cat >>');
    expect($content)->not->toContain('sudo service caddy reload');
});

test('redeploy script includes callback url', function () {
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
    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "=== Redeployment completed successfully ==="');
    expect($content)->toContain('curl -s -X POST "$REPORT_URL"');
    expect($content)->toContain($callbackUrl);
});

test('redeploy script includes error handling', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('reportError()');
    expect($content)->toContain('trap \'reportError "Script failed at line $LINENO"\' ERR');
});

test('redeploy script sets permissions', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Set directory permissions"');
    expect($content)->toContain('chmod -R 775 storage bootstrap/cache database');
    expect($content)->toContain('chown -R fuse:fuse storage bootstrap/cache database');
});

test('redeploy script does not generate app key', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('echo "Generate APP_KEY"');
    expect($content)->not->toContain('php artisan key:generate');
    expect($content)->not->toContain('$PHP artisan key:generate');
});

test('redeploy script does not copy env example', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('echo "Copy .env.example to .env"');
    expect($content)->not->toContain('cp .env.example .env');
});

test('redeploy script puts app in maintenance mode', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Put application in maintenance mode"');
    expect($content)->toContain('$PHP artisan down');
});

test('redeploy script builds frontend assets', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Build frontend assets"');
    expect($content)->toContain('npm install && npm run build');
});

test('redeploy script runs migrations', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Run database migrations"');
    expect($content)->toContain('$PHP artisan migrate --force');
});

test('redeploy script creates storage link', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Create storage link"');
    expect($content)->toContain('$PHP artisan storage:link --force');
});

test('redeploy script caches laravel config', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Cache Laravel configuration"');
    expect($content)->toContain('$PHP artisan config:cache');
    expect($content)->toContain('$PHP artisan route:cache');
    expect($content)->toContain('$PHP artisan view:cache');
});

test('redeploy script restarts php fpm', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Reload PHP-FPM"');
    expect($content)->toContain('sudo service php8.5-fpm reload');
    expect($content)->toContain('flock -w 10 9');
});

test('redeploy script brings app back up', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Bring application back up"');
    expect($content)->toContain('$PHP artisan up');
});

test('redeploy script runs health check', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain('echo "Run health check"');
    expect($content)->toContain('curl -sf -o /dev/null https://$DOMAIN/up || curl -sf -o /dev/null https://$DOMAIN');
    expect($content)->toContain('|| reportError "Health check failed"');
});

test('redeploy script does not set production env', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('echo "Set APP_ENV=production and APP_DEBUG=false"');
    expect($content)->not->toContain("sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env");
    expect($content)->not->toContain("sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env");
});

test('redeploy script does not create sqlite database', function () {
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

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toContain('echo "Create SQLite database"');
    expect($content)->not->toContain('mkdir -p database');
    expect($content)->not->toContain('touch database/database.sqlite');
});

test('redeploy script uses site php version for fpm', function (string $phpVersion) {
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
        'php_version' => $phpVersion,
    ]);

    $url = URL::signedRoute('sites.redeploy-script', ['site' => $site]);

    $response = $this->get($url);

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->toContain("PHP='/usr/bin/php{$phpVersion}'");
    expect($content)->toContain("sudo service php{$phpVersion}-fpm reload");
})->with(['8.2', '8.3', '8.4']);
