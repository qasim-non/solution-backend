<?php

namespace App\Http\Controllers;

use App\Models\Request;
use App\Models\RequestSocialMedia;

class RequestSocialMediaController extends Controller
{
    public function returnSocialMediaAccount(Request $request)
    {
        $accounts = RequestSocialMedia::returnSocialMediaAccounts($request->id);

        return response()->json(['message' => 'Social media accounts retrieved successfully.', 'messages' => $accounts], 200);
    }
}
