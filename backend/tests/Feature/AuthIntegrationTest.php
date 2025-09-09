<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class AuthIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_user_can_register_successfully()
    {
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at'
                    ],
                    'token'
                ]);

        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'name' => $userData['name']
        ]);
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'user' => [
                        'id',
                        'name',
                        'email'
                    ],
                    'token'
                ]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Invalid credentials'
                ]);
    }

    public function test_authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
                ->assertJson([
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]);
    }

    public function test_authenticated_user_can_update_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $newData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ];

        $response = $this->putJson('/api/auth/profile', $newData);

        $response->assertStatus(200)
                ->assertJson([
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com'
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ]);
    }

    public function test_authenticated_user_can_change_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword')
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/change-password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Password updated successfully'
                ]);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Logged out successfully'
                ]);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes()
    {
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);

        $response = $this->putJson('/api/auth/profile', ['name' => 'Test']);
        $response->assertStatus(401);
    }
}