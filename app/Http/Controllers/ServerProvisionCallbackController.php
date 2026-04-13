<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Jobs\TestServerConnectivity;
use App\Models\Server;

class ServerProvisionCallbackController extends Controller
{
    public function __invoke(Server $server)
    {
        if ($server->status === ServerStatus::Provisioned) {
            return response()->json(['status' => 'already_provisioned']);
        }

        $server->status = ServerStatus::Provisioning;
        $server->save();

        TestServerConnectivity::dispatch($server);

        return response()->json(['status' => 'provisioning']);
    }
}
