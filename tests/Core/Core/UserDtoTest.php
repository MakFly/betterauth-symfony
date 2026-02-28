<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Core;

use BetterAuth\Core\DTO\UserDto;
use BetterAuth\Core\Entities\SimpleUser;
use PHPUnit\Framework\TestCase;

class UserDtoTest extends TestCase
{
    public function testToArrayExcludesPasswordByDefault(): void
    {
        $user = SimpleUser::fromArray([
            'id' => 'user-123',
            'email' => 'test@example.com',
            'password_hash' => '$2y$10$hashedpassword',
            'username' => 'Test User',
            'email_verified' => true,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $dto = UserDto::fromUser($user);
        $array = $dto->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayNotHasKey('password', $array, 'Password should be excluded from toArray() by default');
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('emailVerified', $array);
    }

    public function testToArrayIncludesPasswordWhenExplicitlyRequested(): void
    {
        $user = SimpleUser::fromArray([
            'id' => 'user-123',
            'email' => 'test@example.com',
            'password_hash' => '$2y$10$hashedpassword',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $dto = UserDto::fromUser($user);
        $array = $dto->toArray(includeFields: ['password']);

        $this->assertArrayHasKey('password', $array, 'Password should be included when explicitly requested');
        $this->assertSame('$2y$10$hashedpassword', $array['password']);
    }

    public function testToArrayExcludesPasswordWhenExplicitlyExcluded(): void
    {
        $user = SimpleUser::fromArray([
            'id' => 'user-123',
            'email' => 'test@example.com',
            'password_hash' => '$2y$10$hashedpassword',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $dto = UserDto::fromUser($user);
        $array = $dto->toArray(excludeFields: ['password']);

        $this->assertArrayNotHasKey('password', $array, 'Password should be excluded when explicitly excluded');
    }
}

