<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Update the authenticated user's profile.
     *
     * Only the fields present in the request body are updated; user_id is
     * immutable and phone_number/pin are intentionally not updatable here.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $userId = auth('api')->id();

        $user = User::findOrFail($userId);

        $data = collect($request->validated())
            ->only(['first_name', 'last_name', 'address'])
            ->all();

        if ($data !== []) {
            $user->fill($data);
            $user->save();
        }

        return response()->json([
            'status' => 'SUCCESS',
            'result' => [
                'user_id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'address' => $user->address,
                'updated_date' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
