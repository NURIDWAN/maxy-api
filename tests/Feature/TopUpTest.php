<?php

namespace Tests\Feature;

use App\Models\TopUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TopUpTest extends TestCase
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
    public function test_successful_top_up(): void
    {
        $response = $this->postJson('/api/topup', [
            'amount' => 500000,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'result' => [
                    'top_up_id',
                    'amount_top_up',
                    'balance_before',
                    'balance_after',
                    'created_date',
                ],
            ])
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'amount_top_up'  => 500000,
                    'balance_before' => 0,
                    'balance_after'  => 500000,
                ],
            ]);

        $this->assertEquals(500000, $this->user->fresh()->balance);
        $this->assertDatabaseCount('top_ups', 1);
    }

    /** @test */
    public function test_top_up_with_negative_amount_fails(): void
    {
        $this->postJson('/api/topup', [
            'amount' => -1000,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('top_ups', 0);
    }

    /** @test */
    public function test_top_up_with_zero_amount_fails(): void
    {
        $this->postJson('/api/topup', [
            'amount' => 0,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('top_ups', 0);
    }

    /** @test */
    public function test_top_up_without_amount_fails(): void
    {
        $this->postJson('/api/topup', [], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertEquals(0, $this->user->fresh()->balance);
        $this->assertDatabaseCount('top_ups', 0);
    }

    /** @test */
    public function test_top_up_with_invalid_jwt_returns_401(): void
    {
        $this->postJson('/api/topup', [
            'amount' => 500000,
        ], [
            'Authorization' => 'Bearer invalid-token',
        ])->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertDatabaseCount('top_ups', 0);
    }

    /** @test */
    public function test_top_up_without_jwt_returns_401(): void
    {
        $this->postJson('/api/topup', [
            'amount' => 500000,
        ])->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertDatabaseCount('top_ups', 0);
    }

    /** @test */
    public function test_top_up_id_is_uuid(): void
    {
        $response = $this->postJson('/api/topup', [
            'amount' => 500000,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $topUpId = $response->json('result.top_up_id');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $topUpId
        );
    }

    /** @test */
    public function test_balance_before_and_after_are_correct_on_existing_balance(): void
    {
        $this->user->update(['balance' => 250000]);

        $response = $this->postJson('/api/topup', [
            'amount' => 100000,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'balance_before' => 250000,
                    'balance_after'  => 350000,
                ],
            ]);

        $this->assertEquals(350000, $this->user->fresh()->balance);
    }

    /** @test */
    public function test_top_up_transaction_is_stored_in_database(): void
    {
        $response = $this->postJson('/api/topup', [
            'amount' => 500000,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $topUpId = $response->json('result.top_up_id');

        $this->assertDatabaseHas('top_ups', [
            'id'             => $topUpId,
            'user_id'        => $this->user->id,
            'amount_top_up'  => 500000,
            'balance_before' => 0,
            'balance_after'  => 500000,
        ]);

        $topUp = TopUp::find($topUpId);
        $this->assertNotNull($topUp);
        $this->assertEquals($this->user->id, $topUp->user_id);
    }

    /** @test */
    public function test_balance_is_rolled_back_if_transaction_storage_fails(): void
    {
        // Force the TopUp insert to fail by dropping the table mid-request
        \Illuminate\Support\Facades\Schema::dropIfExists('top_ups');

        $this->postJson('/api/topup', [
            'amount' => 500000,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(500);

        // Balance update inside the failed transaction must be rolled back
        $this->assertEquals(0, $this->user->fresh()->balance);
    }
}
