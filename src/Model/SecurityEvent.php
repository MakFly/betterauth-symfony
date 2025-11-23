<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'security_events')]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['event_type'])]
#[ORM\Index(columns: ['severity'])]
#[ORM\Index(columns: ['created_at'])]
class SecurityEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 36)]
    public string $userId;

    #[ORM\Column(type: 'string', length: 50)]
    public string $eventType;

    #[ORM\Column(type: 'string', length: 20)]
    public string $severity;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    public ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $location = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $details = null;
}
