<?php

namespace App\Http\Controllers;

use App\Http\Requests\loginRequest;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(loginRequest $login)
    {
        $loginInfoValidated = $login->validated();

        $admin = Admin::getAdmin($loginInfoValidated);

        if (! $admin || ! Hash::check($loginInfoValidated['password'], $admin['password'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid username or password.',
            ], 401);
        }

        // Keep existing sessions valid so logging in on one device/browser
        // does not silently log out the others.
        $token = $admin->createToken('loginToken')->plainTextToken;

        return response()->json(['message' => 'Admin login successful.', 'token' => $token], 200);
    }
}
