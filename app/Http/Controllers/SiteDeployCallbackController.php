<?php

namespace App\Http\Controllers;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Http\Request;

class SiteDeployCallbackController extends Controller
{
    public function __invoke(Request $request, Site $site)
    {
        $error = $request->input('error');
        $status = $request->input('status');

        if ($error) {
            $site->status = SiteStatus::Failed;
            $site->save();

            return response()->json(['status' => 'failed'], 400);
        }

        if ($status === 'deploying') {
            $site->status = SiteStatus::Deploying;
            $site->save();

            return response()->json(['status' => 'deploying']);
        }

        if ($status === 'deployed') {
            $site->status = SiteStatus::Deployed;
            $site->save();

            return response()->json(['status' => 'deployed']);
        }

        return response()->json(['status' => 'unknown'], 400);
    }
}
