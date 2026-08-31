<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

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

    /**
     * Authenticate a user and return JWT tokens.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $authFailure = response()->json([
            'message' => "Phone number and pin doesn't match.",
        ], 401);

        $user = User::where('phone_number', $request->phone_number)->first();

        if (! $user || ! Hash::check($request->pin, $user->getRawOriginal('pin'))) {
            return $authFailure;
        }

        // Generate short-lived access token (default TTL from config/jwt.php)
        $accessToken = auth('api')->login($user);

        // Generate long-lived refresh token with a custom claim to distinguish it
        $refreshToken = auth('api')->claims(['token_type' => 'refresh'])
            ->setTTL(config('jwt.refresh_ttl', 20160))
            ->login($user);

        return response()->json([
            'status' => 'SUCCESS',
            'result' => [
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
            ],
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(): JsonResponse
    {
        return response()->json([
            'status' => 'SUCCESS',
            'result' => auth('api')->user(),
        ]);
    }
}
