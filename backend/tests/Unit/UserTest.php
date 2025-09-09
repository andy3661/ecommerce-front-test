<?php

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can be created with valid data', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now(),
    ];

    $user = User::create($userData);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('John Doe')
        ->and($user->email)->toBe('john@example.com')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('user email must be unique', function () {
    User::factory()->create(['email' => 'test@example.com']);

    expect(function () {
        User::factory()->create(['email' => 'test@example.com']);
    })->toThrow(Exception::class);
});

test('user password is hashed when created', function () {
    $user = User::factory()->create(['password' => 'plaintext']);

    expect($user->password)->not->toBe('plaintext')
        ->and(Hash::check('plaintext', $user->password))->toBeTrue();
});

test('user can have roles', function () {
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'admin']);

    $user->roles()->attach($role);

    expect($user->roles)->toHaveCount(1)
        ->and($user->roles->first()->name)->toBe('admin');
});

test('user can check if has role', function () {
    $user = User::factory()->create();
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $userRole = Role::factory()->create(['name' => 'user']);

    $user->roles()->attach($adminRole);

    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->hasRole('user'))->toBeFalse();
});

test('user can have addresses', function () {
    $user = User::factory()->create();
    
    $user->addresses()->create([
        'type' => 'billing',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'address_line_1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => 'US'
    ]);

    expect($user->addresses)->toHaveCount(1)
        ->and($user->addresses->first()->type)->toBe('billing');
});

test('user can have orders', function () {
    $user = User::factory()->create();
    
    $user->orders()->create([
        'order_number' => 'ORD-001',
        'status' => 'pending',
        'total_amount' => 99.99,
        'currency' => 'USD'
    ]);

    expect($user->orders)->toHaveCount(1)
        ->and($user->orders->first()->order_number)->toBe('ORD-001');
});

test('user full name accessor works correctly', function () {
    $user = User::factory()->create([
        'name' => 'John Doe'
    ]);

    expect($user->full_name)->toBe('John Doe');
});

test('user can be soft deleted', function () {
    $user = User::factory()->create();
    $userId = $user->id;

    $user->delete();

    expect(User::find($userId))->toBeNull()
        ->and(User::withTrashed()->find($userId))->not->toBeNull();
});