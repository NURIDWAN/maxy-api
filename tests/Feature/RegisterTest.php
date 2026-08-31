<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'first_name'   => 'Guntur',
        'last_name'    => 'Saputro',
        'phone_number' => '0811255501',
        'address'      => 'Jl. Kebon Sirih No. 1',
        'pin'          => '123456',
    ];

    /** @test */
    public function test_successful_registration_returns_correct_structure(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'result' => [
                    'user_id',
                    'first_name',
                    'last_name',
                    'phone_number',
                    'address',
                    'created_date',
                ],
            ])
            ->assertJson([
                'status' => 'SUCCESS',
                'result' => [
                    'first_name'   => 'Guntur',
                    'last_name'    => 'Saputro',
                    'phone_number' => '0811255501',
                    'address'      => 'Jl. Kebon Sirih No. 1',
                ],
            ]);
    }

    /** @test */
    public function test_user_id_is_a_uuid(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload);

        $response->assertStatus(201);

        $userId = $response->json('result.user_id');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $userId
        );
    }

    /** @test */
    public function test_phone_number_is_unique_in_database(): void
    {
        $this->postJson('/api/register', $this->validPayload)->assertStatus(201);

        $this->assertDatabaseCount('users', 1);
    }

    /** @test */
    public function test_duplicate_phone_number_returns_409(): void
    {
        $this->postJson('/api/register', $this->validPayload)->assertStatus(201);

        $response = $this->postJson('/api/register', $this->validPayload);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Phone Number already registered',
            ]);
    }

    /** @test */
    public function test_missing_first_name_fails_validation(): void
    {
        $payload = $this->validPayload;
        unset($payload['first_name']);

        $this->postJson('/api/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    /** @test */
    public function test_missing_last_name_fails_validation(): void
    {
        $payload = $this->validPayload;
        unset($payload['last_name']);

        $this->postJson('/api/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['last_name']);
    }

    /** @test */
    public function test_missing_phone_number_fails_validation(): void
    {
        $payload = $this->validPayload;
        unset($payload['phone_number']);

        $this->postJson('/api/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number']);
    }

    /** @test */
    public function test_missing_address_fails_validation(): void
    {
        $payload = $this->validPayload;
        unset($payload['address']);

        $this->postJson('/api/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    /** @test */
    public function test_missing_pin_fails_validation(): void
    {
        $payload = $this->validPayload;
        unset($payload['pin']);

        $this->postJson('/api/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
    }

    /** @test */
    public function test_pin_is_hashed_in_database(): void
    {
        $this->postJson('/api/register', $this->validPayload)->assertStatus(201);

        $user = User::first();

        $this->assertNotNull($user);
        $this->assertNotEquals('123456', $user->getRawOriginal('pin'));
        $this->assertTrue(Hash::check('123456', $user->getRawOriginal('pin')));
    }

    /** @test */
    public function test_pin_is_not_returned_in_response(): void
    {
        $response = $this->postJson('/api/register', $this->validPayload);

        $response->assertStatus(201);

        $this->assertArrayNotHasKey('pin', $response->json('result'));
        $this->assertStringNotContainsString('pin', json_encode($response->json()));
    }
}
