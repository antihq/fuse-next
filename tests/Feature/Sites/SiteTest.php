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
        'status' => SiteStatus::Pending->value,
    ]);
});

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

test('site creation validates repository is a url', function () {
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
        ->set('repository', 'not-a-valid-url')
        ->call('create')
        ->assertHasErrors(['repository' => 'url']);

    $this->assertDatabaseMissing('sites');
});

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
        'name' => 'Test Server',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('sites.index', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Sites');
    $response->assertSee('Test Server');
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
    $response->assertSee('Sites');
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
    $response->assertSee('Deploy Site');
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
    $response->assertSee('Deploying...');
    $response->assertSee('Your site is being deployed. This may take a few minutes.');
    $response->assertSee('Deploying site...');
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
    $response->assertSee('Site Deployed');
    $response->assertSee('Your site has been deployed successfully.');
    $response->assertSee('Deployment completed successfully');
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
    $response->assertSee('Site Deployed');
    $response->assertSee('Redeploy Site');
    $response->assertSee('SSH to your server as fuse and run this command to redeploy the site');
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
    $response->assertDontSee('Redeploy Site');
    $response->assertDontSee('run this command to redeploy the site');
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
    $response->assertDontSee('run this command to deploy the site');
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
    $response->assertDontSee('Redeploy Site');
    $response->assertDontSee('run this command to redeploy the site');
    $response->assertDontSee('/redeploy-script');
});
