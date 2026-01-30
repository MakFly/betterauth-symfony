<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for registration requests with validation.
 */
final class RegisterRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public readonly string $email,

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(
            min: 8,
            minMessage: 'Password must be at least {{ limit }} characters long'
        )]
        public readonly string $password,

        #[Assert\Length(
            max: 255,
            maxMessage: 'Name cannot exceed {{ limit }} characters'
        )]
        public readonly ?string $name = null,
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
            name: isset($data['name']) && is_string($data['name']) ? $data['name'] : null,
        );
    }
}
