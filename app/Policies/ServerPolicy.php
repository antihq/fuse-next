<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;

class ServerPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team) && $user->hasTeamPermission($team, TeamPermission::ViewServer);
    }

    public function view(User $user, Team $team, Server $server): bool
    {
        return $server->team_id === $team->id
            && $user->belongsToTeam($team)
            && $user->hasTeamPermission($team, TeamPermission::ViewServer);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team) && $user->hasTeamPermission($team, TeamPermission::CreateServer);
    }

    public function delete(User $user, Team $team, Server $server): bool
    {
        return $server->team_id === $team->id
            && $user->belongsToTeam($team)
            && $user->hasTeamPermission($team, TeamPermission::DeleteServer);
    }
}
