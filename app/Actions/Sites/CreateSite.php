<?php

namespace App\Actions\Sites;

use App\Enums\SiteStatus;
use App\Models\Server;
use App\Models\Site;

class CreateSite
{
    public function handle(Server $server, string $domain, string $repository): Site
    {
        return Site::create([
            'server_id' => $server->id,
            'domain' => $domain,
            'repository' => $repository,
            'status' => SiteStatus::Pending,
        ]);
    }
}
