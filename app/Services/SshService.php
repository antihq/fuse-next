<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SshService
{
    private string $privateKeyPath;

    public function __construct()
    {
        $this->privateKeyPath = config('services.server_ssh_private_key_path');
    }

    public function testConnection(Server $server): bool
    {
        $command = [
            'ssh',
            '-i', $this->privateKeyPath,
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=10',
            '-o', 'BatchMode=yes',
            "root@{$server->ip_address}",
            'hostname',
        ];

        $result = Process::run($command);

        if (! $result->successful()) {
            Log::channel('fuse')->info('SSH connection failed', [
                'server_id' => $server->id,
                'ip_address' => $server->ip_address,
                'exit_code' => $result->exitCode(),
                'output' => $result->errorOutput(),
            ]);

            return false;
        }

        $hostname = trim($result->output());

        Log::channel('fuse')->info('SSH connection successful', [
            'server_id' => $server->id,
            'ip_address' => $server->ip_address,
            'hostname' => $hostname,
        ]);

        return true;
    }
}
