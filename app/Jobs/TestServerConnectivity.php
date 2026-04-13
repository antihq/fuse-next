<?php

namespace App\Jobs;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Services\SshService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TestServerConnectivity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public $backoff = [5, 15, 30];

    public function __construct(public Server $server)
    {
        $this->onQueue('default');
    }

    public function handle(SshService $ssh): void
    {
        $connected = $ssh->testConnection($this->server);

        if ($connected) {
            $this->server->status = ServerStatus::Provisioned;
        } else {
            $this->server->status = ServerStatus::Failed;
        }

        $this->server->save();
    }

    public function failed(\Throwable $exception): void
    {
        $this->server->status = ServerStatus::Failed;
        $this->server->save();
    }
}
