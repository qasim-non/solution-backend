<?php

namespace App\Http\Controllers;

use App\Models\SystemType;

class SystemTypeController extends Controller
{
    public function systemTypes()
    {
        $types = SystemType::getTypes();

        return response()->json(['message' => 'System types retrieved successfully.', 'types' => $types], 200);
    }
}
