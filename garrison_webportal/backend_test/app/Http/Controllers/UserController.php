<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get the user information by user ID.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserInformation($id)
    {
        $user = User::with('userInformation')->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user->userInformation);
    }
}