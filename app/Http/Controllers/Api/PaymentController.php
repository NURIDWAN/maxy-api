<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Deduct balance from the authenticated user for a payment.
     */
    public function pay(PaymentRequest $request): JsonResponse
    {
        $userId = auth('api')->id();
        $amount = (int) $request->amount;

        try {
            $payment = DB::transaction(function () use ($userId, $amount, $request) {
                // Lock the user row to prevent race conditions on balance updates
                $user = User::lockForUpdate()->findOrFail($userId);

                if ($user->balance < $amount) {
                    throw new \Exception('INSUFFICIENT_BALANCE');
                }

                $balanceBefore = $user->balance;
                $balanceAfter = $balanceBefore - $amount;

                $user->balance = $balanceAfter;
                $user->save();

                return Payment::create([
                    'user_id' => $userId,
                    'amount' => $amount,
                    'remarks' => $request->remarks,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                ]);
            });

            return response()->json([
                'status' => 'SUCCESS',
                'result' => [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'remarks' => $payment->remarks,
                    'balance_before' => $payment->balance_before,
                    'balance_after' => $payment->balance_after,
                    'created_date' => $payment->created_at->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json([
                    'message' => 'Balance is not enough',
                ], 400);
            }

            throw $e;
        }
    }
}
