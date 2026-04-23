<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Http\Request;

class ServerProvisionCallbackController extends Controller
{
    public function __invoke(Request $request, Server $server)
    {
        if ($server->status === ServerStatus::Provisioned) {
            return response()->json(['status' => 'already_provisioned']);
        }

        if ($request->has('error')) {
            $server->status = ServerStatus::Failed;
            $server->save();

            return response()->json(['status' => 'failed']);
        }

        if ($request->input('status') === 'completed') {
            $server->status = ServerStatus::Provisioned;
            $server->save();

            return response()->json(['status' => 'provisioned']);
        }

        $server->status = ServerStatus::Provisioning;
        $server->save();

        return response()->json(['status' => 'provisioning']);
    }
}
