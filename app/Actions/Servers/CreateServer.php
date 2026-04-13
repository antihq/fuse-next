<?php

namespace App\Actions\Servers;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\Team;

class CreateServer
{
    public function handle(Team $team, string $ipAddress): Server
    {
        $serverCount = $team->servers()->count();
        $name = 'Server '.$serverCount + 1;

        return Server::create([
            'team_id' => $team->id,
            'name' => $name,
            'ip_address' => $ipAddress,
            'status' => ServerStatus::Pending,
        ]);
    }
}
