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

        if ($server->status === ServerStatus::Pending || $server->status === ServerStatus::Failed) {
            $server->status = ServerStatus::Connected;
            $server->save();

            return response()->json(['status' => 'connected']);
        }

        $server->status = ServerStatus::Provisioning;
        $server->save();

        return response()->json(['status' => 'provisioning']);
    }
}
