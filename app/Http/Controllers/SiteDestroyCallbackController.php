<?php

namespace App\Http\Controllers;

use App\Enums\SiteStatus;
use App\Models\Site;
use Illuminate\Http\Request;

class SiteDestroyCallbackController extends Controller
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

        if ($status === 'destroyed') {
            $site->delete();

            return response()->json(['status' => 'destroyed']);
        }

        return response()->json(['status' => 'unknown'], 400);
    }
}
