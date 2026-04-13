<?php

use App\Enums\ServerStatus;
use App\Enums\TeamRole;
use App\Jobs\TestServerConnectivity;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('servers can be created by owner', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $this->actingAs($owner);

    Livewire::test('pages::servers.index')
        ->set('ipAddress', '192.168.1.1')
        ->call('createServer')
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

    Livewire::test('pages::servers.index')
        ->set('ipAddress', '192.168.1.1')
        ->call('createServer')
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

    Livewire::test('pages::servers.index')
        ->set('ipAddress', '')
        ->call('createServer')
        ->assertHasErrors(['ipAddress' => 'required']);

    $this->assertDatabaseMissing('servers');
});

test('server creation validates ip address format', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $this->actingAs($user);

    Livewire::test('pages::servers.index')
        ->set('ipAddress', 'not-a-valid-ip')
        ->call('createServer')
        ->assertHasErrors(['ipAddress' => 'ip']);

    $this->assertDatabaseMissing('servers');
});

test('server name auto-increments', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $this->actingAs($user);

    Livewire::test('pages::servers.index')
        ->set('ipAddress', '192.168.1.1')
        ->call('createServer')
        ->assertHasNoErrors();

    Livewire::test('pages::servers.index')
        ->set('ipAddress', '192.168.1.2')
        ->call('createServer')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('servers', [
        'team_id' => $team->id,
        'ip_address' => '192.168.1.2',
        'name' => 'Server 2',
    ]);
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
    $response->assertSee('Test Server');
    $response->assertSee('192.168.1.100');
});

test('server show page shows provisioning command', function () {
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
    $response->assertSee('/servers/'.$server->id.'/provision-script');
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

test('test connection button dispatches job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Pending,
    ]);

    Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server])
        ->call('testConnection');

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioning);

    Queue::assertPushed(TestServerConnectivity::class, fn ($job) => $job->server->id === $server->id);
});

test('test connection button only visible when pending', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $provisionedServer = Server::factory()->create([
        'team_id' => $team->id,
        'status' => ServerStatus::Provisioned,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $provisionedServer->id]));

    $response->assertOk();
    $response->assertDontSee('Test Connection');
    $response->assertDontSee('Provisioning Command');
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
    $response->assertDontSee('Provisioning Command');
    $response->assertDontSee('SSH to your server as root');
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

test('server show page shows step 2 provisioning when connected', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->connected()->create([
        'team_id' => $team->id,
        'ip_address' => '10.0.0.1',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Step 2: Provision Server');
    $response->assertSee('SSH to your server as root and run this command to install Caddy, MySQL, Valkey, PHP, Composer, and Node.js');
});

test('server show page shows full provision command when connected', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->connected()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('wget --no-verbose -O -');
    $response->assertSee('/servers/'.$server->id.'/full-provision-script');
});

test('server show page shows mark as provisioned button when connected', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->connected()->create([
        'team_id' => $team->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('servers.show', ['current_team' => $team->slug, 'server' => $server->id]));

    $response->assertOk();
    $response->assertSee('Provisioning completed? Mark as Provisioned');
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
    $response->assertSee('Provisioning in Progress');
    $response->assertSee('Your server is being provisioned. This may take a few minutes.');
    $response->assertSee('Installing software...');
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
    $response->assertSee('Provisioning completed? Mark as Provisioned');
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

test('polling is not active when server is connected', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
    $user->switchTeam($team);

    $server = Server::factory()->connected()->create([
        'team_id' => $team->id,
    ]);

    $livewire = Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server]);

    $livewire->assertSet('shouldPoll', false);
});
