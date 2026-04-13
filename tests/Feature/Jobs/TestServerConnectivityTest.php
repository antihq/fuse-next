<?php

use App\Enums\ServerStatus;
use App\Jobs\TestServerConnectivity;
use App\Models\Server;
use App\Services\SshService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ssh = Mockery::mock(SshService::class);
    $this->app->instance(SshService::class, $this->ssh);
});

test('marks server as provisioned when ssh succeeds', function () {
    $server = Server::factory()->create([
        'status' => ServerStatus::Provisioning,
        'ip_address' => '192.168.1.100',
    ]);

    $this->ssh->shouldReceive('testConnection')
        ->once()
        ->with($server)
        ->andReturn(true);

    $job = new TestServerConnectivity($server);
    $job->handle($this->ssh);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Provisioned);
});

test('marks server as failed when ssh fails', function () {
    $server = Server::factory()->create([
        'status' => ServerStatus::Provisioning,
        'ip_address' => '192.168.1.100',
    ]);

    $this->ssh->shouldReceive('testConnection')
        ->once()
        ->with($server)
        ->andReturn(false);

    $job = new TestServerConnectivity($server);
    $job->handle($this->ssh);

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Failed);
});

test('failed method marks server as failed', function () {
    $server = Server::factory()->create([
        'status' => ServerStatus::Provisioning,
        'ip_address' => '192.168.1.100',
    ]);

    $job = new TestServerConnectivity($server);

    $job->failed(new Exception('SSH timeout'));

    $server->refresh();
    expect($server->status)->toBe(ServerStatus::Failed);
});
