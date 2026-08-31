<?php

namespace Tests\Feature;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $receiver;
    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sender = User::factory()->create([
            'balance' => 400000,
        ]);

        $this->receiver = User::factory()->create([
            'balance' => 0,
        ]);

        $this->accessToken = JWTAuth::fromUser($this->sender);
    }

    /** @test */
    public function test_successful_transfer()
    {
        $payload = [
            'target_user' => $this->receiver->id,
            'amount'      => 30000,
            'remarks'     => 'Hadiah Ultah',
        ];

        $response = $this->postJson('/api/transfer', $payload, [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'amount'         => 30000,
                    'remarks'        => 'Hadiah Ultah',
                    'balance_before' => 400000,
                    'balance_after'  => 370000,
                ]
            ]);

        $response->assertJsonStructure([
            'status',
            'result' => [
                'transfer_id',
                'amount',
                'remarks',
                'balance_before',
                'balance_after',
                'created_date',
            ]
        ]);

        // Validate DB changes
        $this->assertEquals(370000, $this->sender->fresh()->balance);
        $this->assertEquals(30000, $this->receiver->fresh()->balance);
        $this->assertDatabaseCount('transfers', 1);

        $transfer = Transfer::first();
        $this->assertEquals($this->sender->id, $transfer->sender_id);
    }

    /** @test */
    public function test_insufficient_balance()
    {
        $payload = [
            'target_user' => $this->receiver->id,
            'amount'      => 500000,
            'remarks'     => 'Too much',
        ];

        $response = $this->postJson('/api/transfer', $payload, [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(400)
            ->assertExactJson([
                'message' => 'Balance is not enough'
            ]);

        // DB unchanged
        $this->assertEquals(400000, $this->sender->fresh()->balance);
        $this->assertEquals(0, $this->receiver->fresh()->balance);
        $this->assertDatabaseCount('transfers', 0);
    }

    /** @test */
    public function test_unauthenticated_request()
    {
        $payload = [
            'target_user' => $this->receiver->id,
            'amount'      => 30000,
        ];

        // Without Bearer token
        $response = $this->postJson('/api/transfer', $payload);

        $response->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated'
            ]);
    }

    /** @test */
    public function test_cannot_transfer_to_self()
    {
        $payload = [
            'target_user' => $this->sender->id,
            'amount'      => 10000,
        ];

        $response = $this->postJson('/api/transfer', $payload, [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Cannot transfer to yourself'
            ]);
    }

    /** @test */
    public function test_invalid_amount_is_rejected()
    {
        $payload = [
            'target_user' => $this->receiver->id,
            'amount'      => 0, // negative or zero amount
        ];

        $response = $this->postJson('/api/transfer', $payload, [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function test_non_existent_target_user_is_rejected()
    {
        $payload = [
            'target_user' => '123e4567-e89b-12d3-a456-426614174000', // valid UUID, not in DB
            'amount'      => 10000,
        ];

        $response = $this->postJson('/api/transfer', $payload, [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_user']);
    }
}
