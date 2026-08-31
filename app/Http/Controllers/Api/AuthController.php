<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        if (User::where('phone_number', $request->phone_number)->exists()) {
            return response()->json([
                'message' => 'Phone Number already registered',
            ], 409);
        }

        $user = User::create([
            'first_name'   => $request->first_name,
            'last_name'    => $request->last_name,
            'phone_number' => $request->phone_number,
            'address'      => $request->address,
            'pin'          => $request->pin,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'result' => [
                'user_id'      => $user->id,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'phone_number' => $user->phone_number,
                'address'      => $user->address,
                'created_date' => $user->created_at->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }
}
