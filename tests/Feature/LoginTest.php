<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $rawPin = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with a known hashed pin
        $this->user = User::factory()->create([
            'phone_number' => '0811255501',
            'pin'          => Hash::make($this->rawPin),
        ]);
    }

    /** @test */
    public function test_successful_login_returns_tokens(): void
    {
        $response = $this->postJson('/api/login', [
            'phone_number' => '0811255501',
            'pin'          => $this->rawPin,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'result' => [
                    'access_token',
                    'refresh_token',
                ],
            ])
            ->assertJson([
                'status' => 'SUCCESS',
            ]);
    }

    /** @test */
    public function test_unknown_phone_number_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'phone_number' => '0899999999',
            'pin'          => $this->rawPin,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => "Phone number and pin doesn't match.",
            ]);
    }

    /** @test */
    public function test_invalid_pin_returns_same_401_as_unknown_phone(): void
    {
        $response = $this->postJson('/api/login', [
            'phone_number' => '0811255501',
            'pin'          => 'wrongpin',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => "Phone number and pin doesn't match.",
            ]);
    }

    /** @test */
    public function test_missing_phone_number_fails_validation(): void
    {
        $response = $this->postJson('/api/login', [
            'pin' => $this->rawPin,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number']);
    }

    /** @test */
    public function test_missing_pin_fails_validation(): void
    {
        $response = $this->postJson('/api/login', [
            'phone_number' => '0811255501',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
    }

    /** @test */
    public function test_access_token_and_refresh_token_are_different(): void
    {
        $response = $this->postJson('/api/login', [
            'phone_number' => '0811255501',
            'pin'          => $this->rawPin,
        ]);

        $accessToken = $response->json('result.access_token');
        $refreshToken = $response->json('result.refresh_token');

        $this->assertNotEmpty($accessToken);
        $this->assertNotEmpty($refreshToken);
        $this->assertNotEquals($accessToken, $refreshToken);
    }

    /** @test */
    public function test_pin_is_never_returned_in_api_response(): void
    {
        $response = $this->postJson('/api/login', [
            'phone_number' => '0811255501',
            'pin'          => $this->rawPin,
        ]);

        $this->assertStringNotContainsString('pin', json_encode($response->json('result')));
    }

    /** @test */
    public function test_can_access_protected_endpoint_with_valid_jwt(): void
    {
        $token = JWTAuth::fromUser($this->user);

        $response = $this->getJson('/api/me', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'SUCCESS',
            ]);
        
        $this->assertEquals($this->user->id, $response->json('result.id'));
    }

    /** @test */
    public function test_cannot_access_protected_endpoint_without_jwt(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }
}
