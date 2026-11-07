<?php

namespace App\Http\Controllers;

use App\Models\Request;

class DashboardController extends Controller
{
    public function dashboardInfo()
    {
        $info = Request::dashboardInfo();

        return response()->json(['message' => 'Dashboard data retrieved successfully.', 'data' => $info], 200);
    }
}
