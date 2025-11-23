<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'suspicious_activities')]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['risk_level'])]
#[ORM\Index(columns: ['detected_at'])]
class SuspiciousActivity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 36)]
    public string $userId;

    #[ORM\Column(type: 'string', length: 50)]
    public string $activityType;

    #[ORM\Column(type: 'string', length: 20)]
    public string $riskLevel;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    public ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $location = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $detectedAt;

    #[ORM\Column(type: 'string', length: 20)]
    public string $status = 'pending';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $details = null;
}
