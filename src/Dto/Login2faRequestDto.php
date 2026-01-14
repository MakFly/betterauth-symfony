<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for 2FA login requests with validation.
 */
final class Login2faRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public readonly string $email,

        #[Assert\NotBlank(message: 'Password is required')]
        public readonly string $password,

        #[Assert\NotBlank(message: '2FA code is required')]
        #[Assert\Length(
            exactly: 6,
            exactMessage: '2FA code must be exactly {{ limit }} digits'
        )]
        #[Assert\Type(type: 'digit', message: '2FA code must contain only digits')]
        public readonly string $code,
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
            code: (string) ($data['code'] ?? ''),
        );
    }
}
