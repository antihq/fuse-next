<?php

use App\Models\Server;
use App\Services\SshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.server_ssh_private_key_path' => '/path/to/key']);
    Process::fake();
});

test('ssh command uses correct private key path', function () {
    $server = Server::factory()->create([
        'ip_address' => '192.168.1.100',
    ]);

    $ssh = app(SshService::class);
    $ssh->testConnection($server);

    Process::assertRan(function ($process) {
        return in_array('-i', $process->command)
            && in_array('/path/to/key', $process->command);
    });
});

test('ssh command uses strict host key checking options', function () {
    $server = Server::factory()->create([
        'ip_address' => '192.168.1.100',
    ]);

    $ssh = app(SshService::class);
    $ssh->testConnection($server);

    Process::assertRan(function ($process) {
        return in_array('-o', $process->command)
            && in_array('StrictHostKeyChecking=no', $process->command)
            && in_array('-o', $process->command)
            && in_array('UserKnownHostsFile=/dev/null', $process->command);
    });
});

test('ssh command uses batch mode and connect timeout', function () {
    $server = Server::factory()->create([
        'ip_address' => '192.168.1.100',
    ]);

    $ssh = app(SshService::class);
    $ssh->testConnection($server);

    Process::assertRan(function ($process) {
        return in_array('-o', $process->command)
            && in_array('BatchMode=yes', $process->command)
            && in_array('-o', $process->command)
            && in_array('ConnectTimeout=10', $process->command);
    });
});

test('ssh command targets root user with server ip address', function () {
    $server = Server::factory()->create([
        'ip_address' => '10.0.0.1',
    ]);

    $ssh = app(SshService::class);
    $ssh->testConnection($server);

    Process::assertRan(function ($process) {
        return in_array('root@10.0.0.1', $process->command);
    });
});

test('ssh command runs hostname', function () {
    $server = Server::factory()->create([
        'ip_address' => '192.168.1.100',
    ]);

    $ssh = app(SshService::class);
    $ssh->testConnection($server);

    Process::assertRan(function ($process) {
        return in_array('hostname', $process->command);
    });
});

test('test connection returns true when ssh succeeds', function () {
    Process::fake([
        '*' => Process::result(output: 'my-server', exitCode: 0),
    ]);

    $server = Server::factory()->create([
        'ip_address' => '192.168.1.100',
    ]);

    $ssh = app(SshService::class);

    $result = $ssh->testConnection($server);

    expect($result)->toBeTrue();
});

test('test connection returns false when ssh fails', function () {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'Connection refused',
            exitCode: 255,
        ),
    ]);

    $server = Server::factory()->create([
        'ip_address' => '192.168.1.100',
    ]);

    $ssh = app(SshService::class);

    $result = $ssh->testConnection($server);

    expect($result)->toBeFalse();
});

test('test connection returns false on non-zero exit code', function () {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'Host unreachable',
            exitCode: 1,
        ),
    ]);

    $server = Server::factory()->create([
        'ip_address' => '192.168.1.100',
    ]);

    $ssh = app(SshService::class);

    $result = $ssh->testConnection($server);

    expect($result)->toBeFalse();
});
