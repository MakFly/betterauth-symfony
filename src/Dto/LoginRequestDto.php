<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for login requests with validation.
 */
final class LoginRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public readonly string $email,

        #[Assert\NotBlank(message: 'Password is required')]
        public readonly string $password,
    ) {
    }

    /**
     * Create from request data array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) ($data['email'] ?? ''),
            password: (string) ($data['password'] ?? ''),
        );
    }
}
