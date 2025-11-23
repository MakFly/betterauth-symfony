<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'guest_sessions')]
#[ORM\Index(columns: ['token'])]
#[ORM\Index(columns: ['expires_at'])]
class GuestSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    public string $token;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $deviceInfo = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    public ?string $ipAddress = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $metadata = null;
}
