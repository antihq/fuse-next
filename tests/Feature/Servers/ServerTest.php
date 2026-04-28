<?php

use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('servers can be created by owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $this->actingAs($owner);

    Livewire::test('pages::servers.create')
        ->set('ipAddress', '192.168.1.1')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('servers', [
        'team_id' => $team->id,
        'ip_address' => '192.168.1.1',
    ]);
});

test('servers cannot be created by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $this->actingAs($member);

    Livewire::test('pages::servers.create')
        ->set('ipAddress', '192.168.1.1')
        ->call('create')
        ->assertForbidden();

    $this->assertDatabaseMissing('servers', [
        'ip_address' => '192.168.1.1',
    ]);
});

test('server creation validates ip address is required', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $this->actingAs($user);

    Livewire::test('pages::servers.create')
        ->set('ipAddress', '')
        ->call('create')
        ->assertHasErrors(['ipAddress' => 'required']);

    $this->assertDatabaseMissing('servers');
});

test('server creation validates ip address format', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $this->actingAs($user);

    Livewire::test('pages::servers.create')
        ->set('ipAddress', 'not-a-valid-ip')
        ->call('create')
        ->assertHasErrors(['ipAddress' => 'ip']);

    $this->assertDatabaseMissing('servers');
});

test('server name auto-increments', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $this->actingAs($user);

    Livewire::test('pages::servers.create')
        ->set('ipAddress', '192.168.1.1')
        ->call('create')
        ->assertHasNoErrors();

    Livewire::test('pages::servers.create')
        ->set('ipAddress', '192.168.1.2')
        ->call('create')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('servers', [
        'team_id' => $team->id,
        'ip_address' => '192.168.1.2',
        'name' => 'Server 2',
    ]);
});

test('server create page can be rendered', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.create', ['current_team' => $team->slug]));

    $response->assertOk();
    $response->assertSee('Connect a new server');
});

test('server create page shows breadcrumbs and prerequisites', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.create', ['current_team' => $team->slug]));

    $response->assertOk();
    $response->assertSee('Servers');
    $response->assertSee('Connect Server');
    $response->assertSee("Enter the public IP address of your VPS. We'll provision everything — Caddy, PHP, queues, and more.");
    $response->assertSee('Before you begin');
    $response->assertSee('Fresh install of Ubuntu 24.04 LTS');
    $response->assertSee('(or latest LTS)');
    $response->assertSee('Root SSH access');
    $response->assertSee('Make sure you can SSH in as root');
    $response->assertSee('Public IP address');
    $response->assertSee('Your VPS must be reachable from the internet');
    $response->assertSee("After connecting, you'll get a one-line script to run that installs Caddy, PHP, Composer, Node.js, and everything else needed to deploy Laravel apps.");
});

test('guests cannot access server create page', function () {
    $response = $this->get(route('servers.create', ['current_team' => 'test-team']));

    $response->assertRedirect(route('login'));
});

test('servers cannot be viewed by non team members', function () {
    $owner = User::factory()->create();
    $nonMember = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($nonMember)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertStatus(403);
});

test('guests cannot access servers', function () {
    $response = $this->get(route('servers.index', ['current_team' => 'test-team']));

    $response->assertRedirect(route('login'));
});

test('server show page can be rendered', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.100',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('192.168.1.100');
});

test('server show page shows provision command when pending', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'ip_address' => '10.0.0.1',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('wget --no-verbose -O -');
    $response->assertSee('/servers/'.$server->id.'/full-provision-script');
});

test('server show page provisioning command contains signature', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('signature=');
});

test('provisioning section hidden when server is provisioned', function () {
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
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertDontSee('Set up your server');
    $response->assertDontSee('SSH into your server as root');
    $response->assertDontSee('Setting up your server');
});

test('refresh server refreshes model status', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server]);

    $server->update(['status' => ServerStatus::Provisioned]);

    $livewire->call('refreshServer');

    $livewire->assertSet('server.status', ServerStatus::Provisioned);
});

test('server show page shows ssh key warning when user has no keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('No SSH keys configured');
});

test('server show page shows team key names when team has keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    SshKey::factory()->create([
        'user_id' => $user->id,
        'name' => 'MacBook Pro',
    ]);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Keys to be authorized');
    $response->assertSee('MacBook Pro');
    $response->assertSee($user->name);
    $response->assertDontSee('No SSH keys configured');
});

test('server show page shows all team member keys with owner names', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->switchTeam($team);

    SshKey::factory()->create(['user_id' => $owner->id, 'name' => 'Owner Laptop']);
    SshKey::factory()->create(['user_id' => $owner->id, 'name' => 'Owner Desktop']);
    SshKey::factory()->create(['user_id' => $member->id, 'name' => 'Member MacBook']);

    $server = Server::factory()->create(['team_id' => $team->id]);

    $response = $this
        ->actingAs($owner)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Keys to be authorized');
    $response->assertSee('Owner Laptop');
    $response->assertSee('Owner Desktop');
    $response->assertSee('Member MacBook');
    $response->assertSee($owner->name);
    $response->assertSee($member->name);
});

test('server show page excludes keys from non team members', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    SshKey::factory()->create(['user_id' => $owner->id, 'name' => 'Owner Key']);
    SshKey::factory()->create(['user_id' => $outsider->id, 'name' => 'Outsider Key']);

    $server = Server::factory()->create(['team_id' => $team->id]);

    $response = $this
        ->actingAs($owner)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Keys to be authorized');
    $response->assertSee('Owner Key');
    $response->assertDontSee('Outsider Key');
});

test('server show page does not show keys table when no team members have keys', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create(['team_id' => $team->id]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertDontSee('Keys to be authorized');
});

test('server show page shows mark as provisioned button when pending', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Mark as ready');
    $response->assertSee("The script reports back when it's done. If it doesn't, you can mark the server as ready manually.");
});

test('server show page shows provisioning in progress when provisioning', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->provisioning()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Mark as ready');
    $response->assertSee("The script reports back when it's done. If it doesn't, you can mark the server as ready manually.");
});

test('server show page shows mark as provisioned button when provisioning', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->provisioning()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Mark as ready');
    $response->assertSee("The script reports back when it's done. If it doesn't, you can mark the server as ready manually.");
});

test('mark provisioned action sets status to provisioned', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->provisioning()->create([
        'team_id' => $team->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server])
        ->call('markProvisioned');

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioned);
});

test('mark provisioned action is forbidden for non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $server = Server::factory()->provisioning()->create([
        'team_id' => $team->id,
    ]);

    Livewire::actingAs($member)
        ->test('pages::servers.show', ['server' => $server])
        ->call('markProvisioned')
        ->assertForbidden();

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioning);
});

test('polling is active when server is pending', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server]);

    $livewire->assertSet('shouldPoll', true);
});

test('polling is active when server is provisioning', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->provisioning()->create([
        'team_id' => $team->id,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server]);

    $livewire->assertSet('shouldPoll', true);
});

test('polling is not active when server is provisioned', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server]);

    $livewire->assertSet('shouldPoll', false);
});

test('server can be deleted by owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $server = Server::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($owner)
        ->test('pages::servers.index')
        ->call('deleteServer', $server->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('servers', ['id' => $server->id]);
});

test('server can be deleted by admin', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $server = Server::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($admin)
        ->test('pages::servers.index')
        ->call('deleteServer', $server->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('servers', ['id' => $server->id]);
});

test('server cannot be deleted by member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $server = Server::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($member)
        ->test('pages::servers.index')
        ->call('deleteServer', $server->id)
        ->assertForbidden();

    $this->assertDatabaseHas('servers', ['id' => $server->id]);
});

test('server show page shows team keys without warning when user has no keys but teammates do', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $owner->switchTeam($team);

    SshKey::factory()->create(['user_id' => $member->id, 'name' => 'Member Laptop']);

    $server = Server::factory()->create(['team_id' => $team->id]);

    $response = $this
        ->actingAs($owner)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('No SSH keys configured');
    $response->assertSee('Member Laptop');
    $response->assertSee('Keys to be authorized');
    $response->assertSee($member->name);
});

test('admin can mark server as provisioned', function () {
    $admin = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $server = Server::factory()->provisioning()->create(['team_id' => $team->id]);

    Livewire::actingAs($admin)
        ->test('pages::servers.show', ['server' => $server])
        ->call('markProvisioned');

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioned);
});

test('server show page renders with failed status', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->failed()->create([
        'team_id' => $team->id,
        'ip_address' => '10.0.0.5',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('10.0.0.5');
    $response->assertSee('Failed');
});

test('polling is active when server has failed', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->failed()->create([
        'team_id' => $team->id,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server]);

    $livewire->assertSet('shouldPoll', true);
});

test('failed server shows retry command and skip button', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->failed()->create([
        'team_id' => $team->id,
        'ip_address' => '10.0.0.5',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Setup failed');
    $response->assertSee('The setup script encountered an error. You can try running the command again or mark the server as ready manually.');
    $response->assertSee('wget --no-verbose -O -');
    $response->assertSee('/servers/'.$server->id.'/full-provision-script');
    $response->assertSee('Mark as ready');
    $response->assertSee("The script reports back when it's done. If it doesn't, you can mark the server as ready manually.");
});

test('mark provisioned works from failed status', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->failed()->create([
        'team_id' => $team->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server])
        ->call('markProvisioned');

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioned);
});

test('mark provisioned from failed status forbidden for non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $server = Server::factory()->failed()->create([
        'team_id' => $team->id,
    ]);

    Livewire::actingAs($member)
        ->test('pages::servers.show', ['server' => $server])
        ->call('markProvisioned')
        ->assertForbidden();

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Failed);
});

test('server index shows empty state when no servers', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.index', ['current_team' => $team->slug]));

    $response->assertOk();
    $response->assertSee('Connect your first server');
    $response->assertSee('You\'ll need a fresh VPS running Ubuntu LTS');
    $response->assertSee('Fresh install of Ubuntu 24.04 LTS (or latest LTS)');
    $response->assertSee('Once set up, you can deploy Laravel sites with a single command');
});

test('server index empty state shows what gets installed section', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.index', ['current_team' => $team->slug]));

    $response->assertOk();
    $response->assertSee('What gets installed');
    $response->assertSee('Caddy 2');
    $response->assertSee('Web server with automatic HTTPS');
    $response->assertSee('PHP 8.2–8.5');
    $response->assertSee('Production-ready runtime');
    $response->assertSee('Node.js 22 LTS');
    $response->assertSee('Frontend build runtime');
    $response->assertSee('Composer 2');
    $response->assertSee('PHP dependency manager');
    $response->assertSee('UFW + fail2ban');
    $response->assertSee('Firewall and intrusion protection');
    $response->assertSee('Unattended upgrades');
    $response->assertSee('Automatic security updates');
    $response->assertSee('Supervisor');
    $response->assertSee('Process manager for queues');
});

test('server index shows server list when servers exist', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    Server::factory()->create([
        'team_id' => $team->id,
        'ip_address' => '192.168.1.100',
        'status' => ServerStatus::Provisioned,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.index', ['current_team' => $team->slug]));

    $response->assertOk();
    $response->assertSee('192.168.1.100');
    $response->assertSee('Connect server');
});

test('server show pending state shows setup heading and description', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'ip_address' => '10.0.0.1',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Set up your server');
    $response->assertSee('SSH into your server as root and run the command below');
});

test('server show provisioned state shows sites section and empty state', function () {
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
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Sites');
    $response->assertSee('This server is ready. Add a site to start deploying your Laravel application.');
    $response->assertSee('Add site');
});

test('server show provisioned state shows sites list', function () {
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

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Sites');
    $response->assertSee('example.com');
    $response->assertSee('Deployed');
});

test('provisioned server shows connect section with ssh commands', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
        'ip_address' => '10.0.0.100',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Connect to your server');
    $response->assertSee('fuse');
    $response->assertSee('root');
    $response->assertSee('SSH into this server as');
    $response->assertSee('to manage your Laravel sites');
    $response->assertSee('ssh fuse@10.0.0.100');
    $response->assertSee('ssh root@10.0.0.100');
});

test('connect to server section not shown when server is pending', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
        'ip_address' => '10.0.0.50',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertDontSee('Connect to your server');
    $response->assertDontSee('ssh fuse@10.0.0.50');
    $response->assertDontSee('ssh root@10.0.0.50');
});
