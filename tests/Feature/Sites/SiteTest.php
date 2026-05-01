<?php

use App\Enums\ServerStatus;
use App\Enums\SiteStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('sites can be created by owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', 'example.com')
        ->set('repository', 'https://github.com/user/repo.git')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'server_id' => $server->id,
        'domain' => 'example.com',
        'repository' => 'https://github.com/user/repo.git',
        'php_version' => '8.5',
        'status' => SiteStatus::Pending->value,
    ]);
});

test('sites can be created with a custom php version', function (string $phpVersion) {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', 'example.com')
        ->set('repository', 'https://github.com/user/repo.git')
        ->set('phpVersion', $phpVersion)
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'server_id' => $server->id,
        'domain' => 'example.com',
        'php_version' => $phpVersion,
    ]);
})->with(['8.2', '8.3', '8.4']);

test('site creation validates php version', function (string $phpVersion) {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', 'example.com')
        ->set('repository', 'https://github.com/user/repo.git')
        ->set('phpVersion', $phpVersion)
        ->call('create')
        ->assertHasErrors(['phpVersion' => 'in']);

    $this->assertDatabaseMissing('sites');
})->with(['7.4', '8.0', '8.1', '9.0']);

test('sites cannot be created by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($member);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', 'example.com')
        ->set('repository', 'https://github.com/user/repo.git')
        ->call('create')
        ->assertForbidden();

    $this->assertDatabaseMissing('sites', [
        'domain' => 'example.com',
    ]);
});

test('site creation validates domain is required', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', '')
        ->set('repository', 'https://github.com/user/repo.git')
        ->call('create')
        ->assertHasErrors(['domain' => 'required']);

    $this->assertDatabaseMissing('sites');
});

test('site creation validates repository is required', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', 'example.com')
        ->set('repository', '')
        ->call('create')
        ->assertHasErrors(['repository' => 'required']);

    $this->assertDatabaseMissing('sites');
});

test('site creation validates repository format', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', 'example.com')
        ->set('repository', 'not-a-valid-repository')
        ->call('create')
        ->assertHasErrors(['repository' => 'regex']);

    $this->assertDatabaseMissing('sites');
});

test('sites accept valid repository formats', function (string $repository) {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', 'example.com')
        ->set('repository', $repository)
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'server_id' => $server->id,
        'repository' => $repository,
    ]);
})->with([
    'HTTPS URL' => 'https://github.com/user/repo.git',
    'HTTP URL' => 'http://github.com/user/repo.git',
    'SSH URL' => 'git@github.com:user/repo.git',
    'SSH without .git suffix' => 'git@github.com:user/repo',
    'dotted repo name' => 'git@gitlab.com:user/repo.name.git',
]);

test('sites reject invalid repository formats', function (string $repository) {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::sites.create', ['server' => $server])
        ->set('domain', 'example.com')
        ->set('repository', $repository)
        ->call('create')
        ->assertHasErrors(['repository' => 'regex']);

    $this->assertDatabaseMissing('sites');
})->with([
    'spaces' => 'git@github.com:user/ repo.git',
    'ftp scheme' => 'ftp://github.com/user/repo.git',
    'bare domain' => 'github.com/user/repo.git',
]);

test('sites cannot be viewed by non team members', function () {
    $owner = User::factory()->create();
    $nonMember = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->create([
        'server_id' => $server->id,
    ]);

    $response = $this
        ->actingAs($nonMember)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertStatus(403);
});

test('guests cannot access sites', function () {
    $response = $this->get(route('sites.index', ['current_team' => 'test-team', 'server' => 1]));

    $response->assertRedirect(route('login'));
});

test('site index page renders for provisioned server', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.index', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
});

test('site index page not accessible for non-provisioned server', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.index', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
});

test('site show page renders with deploy command for pending site', function () {
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
        'status' => SiteStatus::Pending,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('example.com');
    $response->assertSee('https://github.com/user/repo.git');
    $response->assertSee('Deploy your site');
    $response->assertSee('wget --no-verbose -O -');
    $response->assertSee('/sites/'.$site->id.'/deploy-script');
});

test('site show page shows deploying state', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Deploying');
    $response->assertSee('This may take a few minutes.');
    $response->assertSee('Cloning repository, installing dependencies, and configuring Caddy...');
});

test('site show page shows deployed state', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Connect to your site');
    $response->assertSee('SSH into the server to manage your Laravel site.');
});

test('mark deployed action sets status to deployed', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('markDeployed');

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deployed);
});

test('mark deployed action is forbidden for members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($member)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('markDeployed')
        ->assertForbidden();

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deploying);
});

test('polling is active when site is deploying', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site]);

    $livewire->assertSet('shouldPoll', true);
});

test('polling is not active when site is pending', function () {
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

    $livewire = Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site]);

    $livewire->assertSet('shouldPoll', false);
});

test('refresh site refreshes model status', function () {
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

    $livewire = Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site]);

    $site->update(['status' => SiteStatus::Deployed]);

    $livewire->call('refreshSite');

    $livewire->assertSet('site.status', SiteStatus::Deployed);
});

test('site show page shows redeploy command for deployed site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Redeploy site');
    $response->assertSee('Run this command to redeploy your site');
    $response->assertSee('wget --no-verbose -O -');
    $response->assertSee('/sites/'.$site->id.'/redeploy-script');
});

test('redeploy section not shown for pending site', function () {
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

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Redeploy site');
    $response->assertDontSee('Run this command to redeploy your site');
    $response->assertDontSee('/redeploy-script');
});

test('deploy command not shown for deployed site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Deploy Site');
    $response->assertDontSee('Run this command to deploy site');
    $response->assertDontSee('/deploy-script');
});

test('redeploy section not shown for deploying site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Redeploy site');
    $response->assertDontSee('Run this command to redeploy your site');
    $response->assertDontSee('/redeploy-script');
});

test('initiate delete sets status to deleting', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('initiateDelete');

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deleting);
});

test('initiate delete is forbidden for members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($member)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('initiateDelete')
        ->assertForbidden();

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deployed);
});

test('mark deleted deletes site from database and redirects', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deleting()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('markDeleted')
        ->assertRedirect(route('sites.index', [$team->slug, $server]));

    $this->assertDatabaseMissing('sites', ['id' => $site->id]);
});

test('mark deleted is forbidden for members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deleting()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($member)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('markDeleted')
        ->assertForbidden();

    $this->assertDatabaseHas('sites', ['id' => $site->id]);
});

test('delete button is visible for deployed site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Delete site');
});

test('delete button is visible for pending site', function () {
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
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Delete site');
});

test('delete button is visible for failed site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->failed()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Delete site');
});

test('delete button is not visible for deploying site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Delete site');
});

test('delete button is not visible for deleting site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deleting()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Delete site');
});

test('destroy command shown when site is deleting', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deleting()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Remove site');
    $response->assertSee('Run this command on your server to remove the site.');
    $response->assertSee('Mark as deleted');
    $response->assertSee('wget --no-verbose -O -');
    $response->assertSee('/sites/'.$site->id.'/destroy-script');
});

test('destroy command not shown when site is not deleting', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Remove site');
    $response->assertDontSee('Mark as deleted');
    $response->assertDontSee('/destroy-script');
});

test('polling is active when site is deleting', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deleting()->create([
        'server_id' => $server->id,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site]);

    $livewire->assertSet('shouldPoll', true);
});

test('refresh site updates to deleting status', function () {
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

    $livewire = Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site]);

    $site->update(['status' => SiteStatus::Deleting]);

    $livewire->call('refreshSite');

    $livewire->assertSet('site.status', SiteStatus::Deleting);
    $livewire->assertSet('shouldPoll', true);
});

test('failed state shows deployment failed heading and red callout', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->failed()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Deployment failed');
    $response->assertSee('The deploy script encountered an error. You can try running the command again or mark the site as deployed manually.');
    $response->assertSee('wget --no-verbose -O -');
    $response->assertSee('/sites/'.$site->id.'/deploy-script');
    $response->assertSee('Mark as deployed');
});

test('deployed state shows connect section with ssh command', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
        'ip_address' => '10.0.0.100',
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
        'domain' => 'example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Connect to your site');
    $response->assertSee('ssh fuse@10.0.0.100');
});

test('deploying state shows mark as deployed fallback button', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Mark as deployed');
});

test('admin can initiate delete', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('initiateDelete');

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deleting);
});

test('admin can mark deleted', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deleting()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('markDeleted')
        ->assertRedirect(route('sites.index', ['current_team' => $team->slug, $server]));

    $this->assertDatabaseMissing('sites', ['id' => $site->id]);
});

test('admin can mark deployed', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->call('markDeployed');

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Deployed);
});

test('queue supervisor section is visible for deployed site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertSee('Queue Supervisor');
    $response->assertSee('Enable queue worker');
    $response->assertSee('queue-supervisor-script');
});

test('queue supervisor section is not visible for pending site', function () {
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

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Queue Supervisor');
    $response->assertDontSee('queue-supervisor-script');
});

test('queue supervisor section is not visible for deploying site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deploying()->create([
        'server_id' => $server->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Queue Supervisor');
});

test('toggle queue supervisor enables queue and saves', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
    ]);

    expect($site->queue_enabled)->toBeFalse();

    Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->set('queueEnabled', true)
        ->call('toggleQueueSupervisor');

    $site->refresh();
    expect($site->queue_enabled)->toBeTrue();
});

test('toggle queue supervisor disables queue and saves', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->queueEnabled()->create([
        'server_id' => $server->id,
    ]);

    expect($site->queue_enabled)->toBeTrue();

    Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->set('queueEnabled', false)
        ->call('toggleQueueSupervisor');

    $site->refresh();
    expect($site->queue_enabled)->toBeFalse();
});

test('toggle queue supervisor is forbidden for members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($member)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->set('queueEnabled', true)
        ->call('toggleQueueSupervisor')
        ->assertForbidden();

    $site->refresh();
    expect($site->queue_enabled)->toBeFalse();
});

test('queue enabled state syncs on refresh site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site]);

    $livewire->assertSet('queueEnabled', false);

    $site->update(['queue_enabled' => true]);

    $livewire->call('refreshSite');

    $livewire->assertSet('queueEnabled', true);
});

test('admin can toggle queue supervisor', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deployed()->create([
        'server_id' => $server->id,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::sites.show', ['server' => $server, 'site' => $site])
        ->set('queueEnabled', true)
        ->call('toggleQueueSupervisor');

    $site->refresh();
    expect($site->queue_enabled)->toBeTrue();
});

test('queue supervisor section is not visible for failed site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->failed()->create([
        'server_id' => $server->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Queue Supervisor');
    $response->assertDontSee('queue-supervisor-script');
});

test('queue supervisor section is not visible for deleting site', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $site = Site::factory()->deleting()->create([
        'server_id' => $server->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.show', ['current_team' => $team->slug, 'server' => $server->id, 'site' => $site->id]));

    $response->assertOk();
    $response->assertDontSee('Queue Supervisor');
    $response->assertDontSee('queue-supervisor-script');
});
