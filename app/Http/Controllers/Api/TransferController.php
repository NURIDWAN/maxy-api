<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    /**
     * Handle the transfer API request.
     */
    public function transfer(TransferRequest $request): JsonResponse
    {
        $senderId = auth('api')->id();
        $targetUserId = $request->target_user;
        $amount = (int) $request->amount;

        if ($senderId === $targetUserId) {
            return response()->json([
                'message' => 'Cannot transfer to yourself'
            ], 400); // 400 is appropriate for business logic validation format requests natively
        }

        try {
            $transfer = DB::transaction(function () use ($senderId, $targetUserId, $amount, $request) {
                // Prevent deadlocks by acquiring locks in consistent order
                $firstId = strcmp($senderId, $targetUserId) < 0 ? $senderId : $targetUserId;
                $secondId = strcmp($senderId, $targetUserId) < 0 ? $targetUserId : $senderId;

                $firstUser = User::lockForUpdate()->findOrFail($firstId);
                $secondUser = User::lockForUpdate()->findOrFail($secondId);

                $sender = $firstUser->id === $senderId ? $firstUser : $secondUser;
                $targetUser = $firstUser->id === $targetUserId ? $firstUser : $secondUser;

                if ($sender->balance < $amount) {
                    throw new \Exception('INSUFFICIENT_BALANCE');
                }

                $balanceBefore = $sender->balance;
                $balanceAfter = $balanceBefore - $amount;

                // Adjust balances
                $sender->balance -= $amount;
                $sender->save();

                $targetUser->balance += $amount;
                $targetUser->save();

                // Record transfer
                return Transfer::create([
                    'sender_id'      => $senderId,
                    'target_user_id' => $targetUserId,
                    'amount'         => $amount,
                    'remarks'        => $request->remarks,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                ]);
            });

            return response()->json([
                'status' => 'SUCCESS',
                'result' => [
                    'transfer_id'    => $transfer->id,
                    'amount'         => $transfer->amount,
                    'remarks'        => $transfer->remarks,
                    'balance_before' => $transfer->balance_before,
                    'balance_after'  => $transfer->balance_after,
                    'created_date'   => $transfer->created_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json([
                    'message' => 'Balance is not enough'
                ], 400);
            }

            // Rethrow unexpected exceptions
            throw $e;
        }
    }
}
