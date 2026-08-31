<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Feature tests for PUT /api/profile.
 *
 * Verifies partial profile updates (only sent fields change), the response
 * contract, immutability of user identity fields, and authentication.
 */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'first_name' => 'Tom',
            'last_name' => 'Araya',
            'address' => 'Jl. Diponegoro No. 215',
        ]);

        $this->accessToken = JWTAuth::fromUser($this->user);
    }

    /** @test */
    public function test_update_first_name_only(): void
    {
        $response = $this->putJson('/api/profile', [
            'first_name' => 'Budi',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'first_name' => 'Budi',
                    'last_name' => 'Araya',
                    'address' => 'Jl. Diponegoro No. 215',
                ],
            ]);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $response->json('result.updated_date')
        );

        $this->assertEquals('Budi', $this->user->fresh()->first_name);
        $this->assertEquals('Araya', $this->user->fresh()->last_name);
    }

    /** @test */
    public function test_update_last_name_and_address(): void
    {
        $response = $this->putJson('/api/profile', [
            'last_name' => 'Hetfield',
            'address' => 'Jl. Merdeka No. 10',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'first_name' => 'Tom',
                    'last_name' => 'Hetfield',
                    'address' => 'Jl. Merdeka No. 10',
                ],
            ]);

        $this->assertEquals('Tom', $this->user->fresh()->first_name);
        $this->assertEquals('Hetfield', $this->user->fresh()->last_name);
        $this->assertEquals('Jl. Merdeka No. 10', $this->user->fresh()->address);
    }

    /** @test */
    public function test_update_all_fields(): void
    {
        $response = $this->putJson('/api/profile', [
            'first_name' => 'Kirk',
            'last_name' => 'Hammett',
            'address' => 'Jl. Sudirman No. 1',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'user_id' => $this->user->id,
                    'first_name' => 'Kirk',
                    'last_name' => 'Hammett',
                    'address' => 'Jl. Sudirman No. 1',
                ],
            ]);

        $fresh = $this->user->fresh();
        $this->assertEquals('Kirk', $fresh->first_name);
        $this->assertEquals('Hammett', $fresh->last_name);
        $this->assertEquals('Jl. Sudirman No. 1', $fresh->address);
    }

    /** @test */
    public function test_response_contains_only_specified_fields(): void
    {
        $response = $this->putJson('/api/profile', [
            'first_name' => 'Budi',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'result' => [
                    'user_id',
                    'first_name',
                    'last_name',
                    'address',
                    'updated_date',
                ],
            ]);

        $this->assertEquals(
            ['status', 'result'],
            array_keys($response->json())
        );

        $this->assertEquals(
            ['user_id', 'first_name', 'last_name', 'address', 'updated_date'],
            array_keys($response->json('result'))
        );
    }

    /** @test */
    public function test_user_id_and_phone_number_are_immutable(): void
    {
        $originalId = $this->user->id;
        $originalPhone = $this->user->phone_number;
        $originalPin = $this->user->getRawOriginal('pin');

        $this->putJson('/api/profile', [
            'user_id' => 'bc1c823e-b0fb-4b20-88c0-dff25e283252',
            'phone_number' => '081111111111',
            'pin' => '654321',
            'first_name' => 'Budi',
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(200);

        $fresh = $this->user->fresh();
        $this->assertEquals($originalId, $fresh->id);
        $this->assertEquals($originalPhone, $fresh->phone_number);
        $this->assertEquals($originalPin, $fresh->getRawOriginal('pin'));
        $this->assertEquals('Budi', $fresh->first_name);
    }

    /** @test */
    public function test_empty_body_returns_profile_unchanged(): void
    {
        $response = $this->putJson('/api/profile', [], [
            'Authorization' => "Bearer {$this->accessToken}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'user_id' => $this->user->id,
                    'first_name' => 'Tom',
                    'last_name' => 'Araya',
                    'address' => 'Jl. Diponegoro No. 215',
                ],
            ]);

        $fresh = $this->user->fresh();
        $this->assertEquals('Tom', $fresh->first_name);
        $this->assertEquals('Araya', $fresh->last_name);
        $this->assertEquals('Jl. Diponegoro No. 215', $fresh->address);
    }

    /** @test */
    public function test_first_name_too_long_fails_validation(): void
    {
        $this->putJson('/api/profile', [
            'first_name' => str_repeat('a', 256),
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);

        $this->assertEquals('Tom', $this->user->fresh()->first_name);
    }

    /** @test */
    public function test_address_too_long_fails_validation(): void
    {
        $this->putJson('/api/profile', [
            'address' => str_repeat('a', 256),
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['address']);

        $this->assertEquals('Jl. Diponegoro No. 215', $this->user->fresh()->address);
    }

    /** @test */
    public function test_non_string_first_name_fails_validation(): void
    {
        $this->putJson('/api/profile', [
            'first_name' => 123,
        ], [
            'Authorization' => "Bearer {$this->accessToken}",
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);

        $this->assertEquals('Tom', $this->user->fresh()->first_name);
    }

    /** @test */
    public function test_update_without_jwt_returns_401(): void
    {
        $this->putJson('/api/profile', [
            'first_name' => 'Budi',
        ])->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertEquals('Tom', $this->user->fresh()->first_name);
    }

    /** @test */
    public function test_update_with_invalid_jwt_returns_401(): void
    {
        $this->putJson('/api/profile', [
            'first_name' => 'Budi',
        ], [
            'Authorization' => 'Bearer invalid-token',
        ])->assertStatus(401)
            ->assertExactJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertEquals('Tom', $this->user->fresh()->first_name);
    }
}
