<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\TopUp;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Feature tests for GET /api/transactions/{id}.
 *
 * Verifies the transaction detail contract for every wallet transaction
 * type (transfer, top-up, payment) including ownership rules, formatting,
 * and authentication behaviour.
 */
class TransactionDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->accessToken = JWTAuth::fromUser($this->user);
    }

    /** @test */
    public function test_payment_detail_returns_formatted_debit_transaction(): void
    {
        $payment = Payment::create([
            'user_id' => $this->user->id,
            'amount' => 30000,
            'remarks' => 'Hadiah Ultah',
            'balance_before' => 400000,
            'balance_after' => 370000,
        ]);

        $response = $this->getJson("/api/transactions/{$payment->id}", [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'transaction_detail' => [
                    'transaction_id' => $payment->id,
                    'transaction_type' => 'DEBIT',
                    'amount' => 30000,
                    'amount_formatted' => 'Rp30.000',
                    'remarks' => 'Hadiah Ultah',
                    'balance_before' => 400000,
                    'balance_before_formatted' => 'Rp400.000',
                    'balance_after' => 370000,
                    'balance_after_formatted' => 'Rp370.000',
                    'status' => 'SUCCESS',
                ],
            ]);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $response->json('transaction_detail.created_date')
        );
    }

    /** @test */
    public function test_top_up_detail_returns_formatted_credit_transaction(): void
    {
        $topUp = TopUp::create([
            'user_id' => $this->user->id,
            'amount_top_up' => 500000,
            'balance_before' => 0,
            'balance_after' => 500000,
        ]);

        $response = $this->getJson("/api/transactions/{$topUp->id}", [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'transaction_detail' => [
                    'transaction_id' => $topUp->id,
                    'transaction_type' => 'CREDIT',
                    'amount' => 500000,
                    'amount_formatted' => 'Rp500.000',
                    'balance_before' => 0,
                    'balance_after' => 500000,
                    'status' => 'SUCCESS',
                ],
            ]);

        // Top-ups have no remarks field.
        $this->assertNull($response->json('transaction_detail.remarks'));
    }

    /** @test */
    public function test_outgoing_transfer_detail_is_debit(): void
    {
        $transfer = Transfer::create([
            'sender_id' => $this->user->id,
            'target_user_id' => $this->otherUser->id,
            'amount' => 150000,
            'remarks' => 'Makan siang',
            'balance_before' => 500000,
            'balance_after' => 350000,
        ]);

        $this->getJson("/api/transactions/{$transfer->id}", [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'transaction_detail' => [
                    'transaction_id' => $transfer->id,
                    'transaction_type' => 'DEBIT',
                    'amount' => 150000,
                    'amount_formatted' => 'Rp150.000',
                    'remarks' => 'Makan siang',
                    'status' => 'SUCCESS',
                ],
            ]);
    }

    /** @test */
    public function test_incoming_transfer_detail_is_credit(): void
    {
        $transfer = Transfer::create([
            'sender_id' => $this->otherUser->id,
            'target_user_id' => $this->user->id,
            'amount' => 200000,
            'remarks' => 'Bayar utang',
            'balance_before' => 100000,
            'balance_after' => 300000,
        ]);

        $this->getJson("/api/transactions/{$transfer->id}", [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'transaction_detail' => [
                    'transaction_type' => 'CREDIT',
                    'amount' => 200000,
                    'amount_formatted' => 'Rp200.000',
                    'balance_before_formatted' => 'Rp100.000',
                    'balance_after_formatted' => 'Rp300.000',
                ],
            ]);
    }

    /** @test */
    public function test_transaction_of_other_user_returns_404(): void
    {
        $payment = Payment::create([
            'user_id' => $this->otherUser->id,
            'amount' => 30000,
            'remarks' => 'Rahasia',
            'balance_before' => 400000,
            'balance_after' => 370000,
        ]);

        $this->getJson("/api/transactions/{$payment->id}", [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(404)
            ->assertExactJson([
                'message' => 'Transaction not found',
            ]);
    }

    /** @test */
    public function test_unknown_transaction_id_returns_404(): void
    {
        $this->getJson('/api/transactions/a7d39cf6-44b6-41fc-b3e9-7b16df5321c5', [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(404)
            ->assertExactJson([
                'message' => 'Transaction not found',
            ]);
    }

    /** @test */
    public function test_detail_without_jwt_returns_401(): void
    {
        $this->getJson('/api/transactions/a7d39cf6-44b6-41fc-b3e9-7b16df5321c5')
            ->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated',
            ]);
    }

    /** @test */
    public function test_detail_with_invalid_jwt_returns_401(): void
    {
        $this->getJson('/api/transactions/a7d39cf6-44b6-41fc-b3e9-7b16df5321c5', [
            'Authorization' => 'Bearer invalid-token',
        ])->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated',
            ]);
    }

    /** @test */
    public function test_large_amounts_are_formatted_with_thousand_separators(): void
    {
        $topUp = TopUp::create([
            'user_id' => $this->user->id,
            'amount_top_up' => 1250000,
            'balance_before' => 1000000,
            'balance_after' => 2250000,
        ]);

        $this->getJson("/api/transactions/{$topUp->id}", [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(200)
            ->assertJson([
                'transaction_detail' => [
                    'amount_formatted' => 'Rp1.250.000',
                    'balance_before_formatted' => 'Rp1.000.000',
                    'balance_after_formatted' => 'Rp2.250.000',
                ],
            ]);
    }
}
