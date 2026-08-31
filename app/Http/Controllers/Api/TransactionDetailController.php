<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TopUp;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class TransactionDetailController extends Controller
{
    private const CREDIT = 'CREDIT';

    private const DEBIT = 'DEBIT';

    /**
     * Get detail of a single transaction owned by the authenticated user.
     *
     * The transaction id may belong to any of the wallet transaction types:
     * transfer, top-up, or payment.
     */
    public function show(string $transactionId): JsonResponse
    {
        $userId = auth('api')->id();

        $transaction = $this->findTransaction($transactionId, $userId);

        if ($transaction === null) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        }

        [$transactionType, $amount] = match (true) {
            $transaction['model'] instanceof TopUp => [self::CREDIT, $transaction['model']->amount_top_up],
            $transaction['model'] instanceof Transfer => [
                $transaction['model']->sender_id === $userId ? self::DEBIT : self::CREDIT,
                $transaction['model']->amount,
            ],
            default => [self::DEBIT, $transaction['model']->amount],
        };

        return response()->json([
            'status' => 'SUCCESS',
            'transaction_detail' => [
                'transaction_id' => $transaction['model']->id,
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'amount_formatted' => $this->formatRupiah($amount),
                'remarks' => $transaction['model']->remarks,
                'balance_before' => $transaction['model']->balance_before,
                'balance_before_formatted' => $this->formatRupiah($transaction['model']->balance_before),
                'balance_after' => $transaction['model']->balance_after,
                'balance_after_formatted' => $this->formatRupiah($transaction['model']->balance_after),
                'status' => 'SUCCESS',
                'created_date' => $transaction['model']->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Locate a transaction by id across the wallet transaction tables.
     *
     * @return array{type: string, model: Transfer|TopUp|Payment}|null
     */
    private function findTransaction(string $transactionId, string $userId): ?array
    {
        $transfer = Transfer::where('id', $transactionId)
            ->where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                    ->orWhere('target_user_id', $userId);
            })
            ->first();

        if ($transfer !== null) {
            // Money leaving the sender's wallet is a DEBIT for the requester.
            // A transfer received from another user is a CREDIT.
            return [
                'type' => $transfer->sender_id === $userId ? self::DEBIT : self::CREDIT,
                'model' => $transfer,
            ];
        }

        $topUp = TopUp::where('id', $transactionId)
            ->where('user_id', $userId)
            ->first();

        if ($topUp !== null) {
            return [
                'type' => self::CREDIT,
                'model' => $topUp,
            ];
        }

        $payment = Payment::where('id', $transactionId)
            ->where('user_id', $userId)
            ->first();

        if ($payment !== null) {
            return [
                'type' => self::DEBIT,
                'model' => $payment,
            ];
        }

        return null;
    }

    private function formatRupiah(int $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }
}
