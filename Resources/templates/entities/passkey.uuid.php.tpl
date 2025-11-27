<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Passkey entity for WebAuthn passwordless authentication.
 */
#[ORM\Entity]
#[ORM\Table(name: 'passkeys')]
#[ORM\Index(columns: ['user_id'], name: 'idx_passkeys_user')]
#[ORM\UniqueConstraint(name: 'uniq_passkeys_credential_id', columns: ['credential_id'])]
class Passkey
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $userId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $credentialId;

    #[ORM\Column(type: 'text')]
    private string $publicKey;

    #[ORM\Column(type: 'integer')]
    private int $signCount = 0;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $transports = null; // usb, nfc, ble, internal

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $attestationType = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aaguid = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function setUserId(Uuid $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): static
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): static
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    public function getSignCount(): int
    {
        return $this->signCount;
    }

    public function setSignCount(int $signCount): static
    {
        $this->signCount = $signCount;
        return $this;
    }

    public function incrementSignCount(): static
    {
        $this->signCount++;
        $this->lastUsedAt = new DateTimeImmutable();
        return $this;
    }

    public function getTransports(): ?string
    {
        return $this->transports;
    }

    public function setTransports(?string $transports): static
    {
        $this->transports = $transports;
        return $this;
    }

    public function getAttestationType(): ?string
    {
        return $this->attestationType;
    }

    public function setAttestationType(?string $attestationType): static
    {
        $this->attestationType = $attestationType;
        return $this;
    }

    public function getAaguid(): ?string
    {
        return $this->aaguid;
    }

    public function setAaguid(?string $aaguid): static
    {
        $this->aaguid = $aaguid;
        return $this;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }
}

