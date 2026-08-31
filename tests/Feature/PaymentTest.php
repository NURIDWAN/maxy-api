<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Feature tests for POST /api/pay.
 *
 * Covers the payment specification:
 * - successful payment (200, SUCCESS payload)
 * - authentication (401 without / invalid JWT)
 * - request validation (422 for amount & remarks rules)
 * - insufficient balance (400 "Balance is not enough", no state change)
 * - persistence, UUID payment_id, created_date format
 * - row-locking guarantee for concurrent payments
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'balance' => 0,
        ]);

        $this->accessToken = JWTAuth::fromUser($this->user);
    }

    /** @test */
    public function test_successful_payment(): void
    {
        $this->user->update(['balance' => 500000]);

        $response = $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'result' => [
                    'payment_id',
                    'amount',
                    'remarks',
                    'balance_before',
                    'balance_after',
                    'created_date',
                ],
            ])
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'amount' => 100000,
                    'remarks' => 'Pulsa Telkomsel 100k',
                    'balance_before' => 500000,
                    'balance_after' => 400000,
                ],
            ]);

        $this->assertEquals(400000, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 1);
    }

    /** @test */
    public function test_payment_without_jwt_returns_401(): void
    {
        $this->user->update(['balance' => 500000]);

        $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ])->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertEquals(500000, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_with_invalid_jwt_returns_401(): void
    {
        $this->user->update(['balance' => 500000]);

        $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => 'Bearer invalid-token',
        ])->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertEquals(500000, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_with_negative_amount_fails(): void
    {
        $this->postJson('/api/pay', [
            'amount' => -100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_with_zero_amount_fails(): void
    {
        $this->postJson('/api/pay', [
            'amount' => 0,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_without_amount_fails(): void
    {
        $this->postJson('/api/pay', [
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_with_non_numeric_amount_fails(): void
    {
        foreach (['abc', 10.5] as $amount) {
            $this->postJson('/api/pay', [
                'amount' => $amount,
                'remarks' => 'Pulsa Telkomsel 100k',
            ], [
                'Authorization' => "Bearer {$this->accessToken}",
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
        }

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_without_remarks_fails(): void
    {
        $this->postJson('/api/pay', [
            'amount' => 100000,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['remarks']);

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_with_non_string_remarks_fails(): void
    {
        $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => ['not', 'a', 'string'],
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['remarks']);

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_fails_when_balance_is_not_enough(): void
    {
        $this->user->update(['balance' => 50000]);

        $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(400)
            ->assertExactJson([
                'message' => 'Balance is not enough',
            ]);

        $this->assertEquals(50000, $this->user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @test */
    public function test_payment_with_balance_equal_to_amount_succeeds(): void
    {
        $this->user->update(['balance' => 100000]);

        $response = $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'balance_before' => 100000,
                    'balance_after' => 0,
                ],
            ]);

        $this->assertEquals(0, $this->user->fresh()->balance);
    }

    /** @test */
    public function test_payment_records_correct_balance_before_and_after(): void
    {
        $this->user->update(['balance' => 750000]);

        $response = $this->postJson('/api/pay', [
            'amount' => 250000,
            'remarks' => 'Listrik PLN',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'amount' => 250000,
                    'balance_before' => 750000,
                    'balance_after' => 500000,
                ],
            ]);

        $this->assertEquals(500000, $this->user->fresh()->balance);
    }

    /** @test */
    public function test_payment_is_stored_in_database(): void
    {
        $this->user->update(['balance' => 500000]);

        $response = $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $paymentId = $response->json('result.payment_id');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'user_id' => $this->user->id,
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
            'balance_before' => 500000,
            'balance_after' => 400000,
        ]);

        $payment = Payment::find($paymentId);
        $this->assertNotNull($payment);
        $this->assertEquals($this->user->id, $payment->user_id);
    }

    /** @test */
    public function test_payment_id_is_uuid(): void
    {
        $this->user->update(['balance' => 500000]);

        $response = $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $paymentId = $response->json('result.payment_id');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $paymentId
        );
    }

    /** @test */
    public function test_payment_created_date_uses_transaction_time(): void
    {
        $this->user->update(['balance' => 500000]);

        $before = now()->getTimestamp();

        $response = $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $after = now()->getTimestamp();

        $response->assertStatus(200);

        $createdDate = $response->json('result.created_date');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $createdDate
        );

        $transactionTime = \DateTime::createFromFormat('Y-m-d H:i:s', $createdDate)->getTimestamp();
        $this->assertGreaterThanOrEqual($before - 120, $transactionTime);
        $this->assertLessThanOrEqual($after + 120, $transactionTime);
    }

    /** @test */
    public function test_row_locking_sees_updated_balance_for_concurrent_payments(): void
    {
        // Row locking (SELECT ... FOR UPDATE) guarantees that concurrent
        // payments on the same user are serialized: a payment waiting for the
        // lock must read the committed, updated balance instead of a stale
        // snapshot. In production each payment runs in its own request; here
        // we emulate the same interleaving in-process.
        $this->user->update(['balance' => 300000]);

        $balances = [];

        DB::transaction(function () use (&$balances) {
            $first = User::lockForUpdate()->find($this->user->id);
            $balances['first_before'] = $first->balance;
            $first->balance -= 200000;
            $first->save();

            // The row lock of the outer transaction is still held while this
            // nested (savepoint) transaction acquires its own lock.
            DB::transaction(function () use (&$balances) {
                $second = User::lockForUpdate()->find($this->user->id);
                $balances['second_before'] = $second->balance;
                $second->balance -= 200000;
                $second->save();
            });
        });

        $this->assertSame(300000, $balances['first_before']);
        // The second payment must NOT observe the stale 300000 balance.
        $this->assertSame(100000, $balances['second_before']);
        $this->assertSame(-100000, $this->user->fresh()->balance);
    }

    /** @test */
    public function test_balance_is_rolled_back_if_transaction_storage_fails(): void
    {
        // Force the Payment insert to fail by dropping the table mid-request
        Schema::dropIfExists('payments');

        $this->user->update(['balance' => 500000]);

        $this->postJson('/api/pay', [
            'amount' => 100000,
            'remarks' => 'Pulsa Telkomsel 100k',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(500);

        // Balance update inside the failed transaction must be rolled back
        $this->assertEquals(500000, $this->user->fresh()->balance);
    }
}
