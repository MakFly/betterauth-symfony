<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'account_links')]
#[ORM\UniqueConstraint(name: 'user_provider_unique', columns: ['user_id', 'provider'])]
#[ORM\Index(columns: ['provider', 'provider_id'])]
class AccountLink
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 36)]
    public string $userId;

    #[ORM\Column(type: 'string', length: 50)]
    public string $provider;

    #[ORM\Column(type: 'string', length: 255)]
    public string $providerId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $providerEmail = null;

    #[ORM\Column(type: 'boolean')]
    public bool $isPrimary = false;

    #[ORM\Column(type: 'string', length: 20)]
    public string $status = 'verified';

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $linkedAt;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $metadata = null;
}
