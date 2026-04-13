<?php

use App\Enums\TeamRole;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
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
