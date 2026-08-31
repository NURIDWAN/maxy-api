<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TopUpRequest;
use App\Models\TopUp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TopUpController extends Controller
{
    /**
     * Add balance to the authenticated user.
     */
    public function topUp(TopUpRequest $request): JsonResponse
    {
        $userId = auth('api')->id();
        $amount = (int) $request->amount;

        $topUp = DB::transaction(function () use ($userId, $amount) {
            // Lock the user row to prevent race conditions on balance updates
            $user = User::lockForUpdate()->findOrFail($userId);

            $balanceBefore = $user->balance;
            $balanceAfter = $balanceBefore + $amount;

            $user->balance = $balanceAfter;
            $user->save();

            return TopUp::create([
                'user_id'        => $userId,
                'amount_top_up'  => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
            ]);
        });

        return response()->json([
            'status' => 'SUCCESS',
            'result' => [
                'top_up_id'     => $topUp->id,
                'amount_top_up' => $topUp->amount_top_up,
                'balance_before' => $topUp->balance_before,
                'balance_after' => $topUp->balance_after,
                'created_date'  => $topUp->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
