<?php

declare(strict_types=1);

namespace Demo\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'demo_account')]
final class DemoAccount implements UserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private int $id = 0;

    #[ORM\Column(length: 254, unique: true)]
    private string $email;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash;

    public function __construct(string $email, string $password)
    {
        $this->email = strtolower($email);
        $this->passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    }

    public function id(): int { return $this->id; }
    public function email(): string { return $this->email; }
    public function passwordHash(): string { return $this->passwordHash; }
    public function changePassword(string $password): void { $this->passwordHash = password_hash($password, PASSWORD_ARGON2ID); }
    public function getUserIdentifier(): string { return (string) $this->id; }
    /** @return list<string> */
    public function getRoles(): array { return ['ROLE_USER']; }
    public function eraseCredentials(): void {}
}
