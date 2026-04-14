<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user, Team $team, Server $server): bool
    {
        return $server->team_id === $team->id
            && $user->belongsToTeam($team)
            && $user->hasTeamPermission($team, TeamPermission::ViewSite);
    }

    public function view(User $user, Team $team, Site $site): bool
    {
        return $site->server->team_id === $team->id
            && $user->belongsToTeam($team)
            && $user->hasTeamPermission($team, TeamPermission::ViewSite);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team) && $user->hasTeamPermission($team, TeamPermission::CreateSite);
    }

    public function update(User $user, Team $team, Site $site): bool
    {
        return $site->server->team_id === $team->id
            && $user->belongsToTeam($team)
            && $user->hasTeamPermission($team, TeamPermission::CreateSite);
    }

    public function delete(User $user, Team $team, Site $site): bool
    {
        return $site->server->team_id === $team->id
            && $user->belongsToTeam($team)
            && $user->hasTeamPermission($team, TeamPermission::DeleteSite);
    }
}
