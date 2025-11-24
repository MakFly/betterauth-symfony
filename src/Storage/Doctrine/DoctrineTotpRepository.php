<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\TotpData;
use BetterAuth\Core\Interfaces\TotpStorageInterface;
use BetterAuth\Symfony\Service\UserIdConverter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine implementation of TotpStorageInterface.
 *
 * This repository is final to ensure consistent TOTP data persistence behavior.
 */
final class DoctrineTotpRepository implements TotpStorageInterface
{
    private string $totpClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        string $totpClass = TotpData::class
    ) {
        $this->totpClass = $totpClass;
    }

    public function store(string $userId, string $secret, array $metadata = []): bool
    {
        $doctrineTotp = $this->entityManager->getRepository($this->totpClass)
            ->findOneBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        if ($doctrineTotp === null) {
            $doctrineTotp = new ($this->totpClass)();
            $doctrineTotp->setUserId($this->idConverter->toDatabaseId($userId));
        }

        $doctrineTotp->setSecret($secret);
        $doctrineTotp->setEnabled($metadata['enabled'] ?? false);
        $doctrineTotp->setBackupCodes($metadata['backup_codes'] ?? []);

        if ($doctrineTotp->getId() === null) {
            $this->entityManager->persist($doctrineTotp);
        }
        $this->entityManager->flush();

        return true;
    }

    public function findByUserId(string $userId): ?array
    {
        $doctrineTotp = $this->entityManager->getRepository($this->totpClass)
            ->findOneBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        if ($doctrineTotp === null) {
            return null;
        }

        return [
            'secret' => $doctrineTotp->getSecret(),
            'enabled' => $doctrineTotp->isEnabled(),
            'backup_codes' => $doctrineTotp->getBackupCodes(),
        ];
    }

    public function isEnabled(string $userId): bool
    {
        $doctrineTotp = $this->entityManager->getRepository($this->totpClass)
            ->findOneBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        return $doctrineTotp !== null && $doctrineTotp->isEnabled();
    }

    public function enable(string $userId): bool
    {
        $doctrineTotp = $this->entityManager->getRepository($this->totpClass)
            ->findOneBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        if ($doctrineTotp === null) {
            return false;
        }

        $doctrineTotp->setEnabled(true);
        $this->entityManager->flush();

        return true;
    }

    public function disable(string $userId): bool
    {
        $doctrineTotp = $this->entityManager->getRepository($this->totpClass)
            ->findOneBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        if ($doctrineTotp === null) {
            return false;
        }

        $doctrineTotp->setEnabled(false);
        $this->entityManager->flush();

        return true;
    }

    public function delete(string $userId): bool
    {
        $doctrineTotp = $this->entityManager->getRepository($this->totpClass)
            ->findOneBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        if ($doctrineTotp === null) {
            return false;
        }

        $this->entityManager->remove($doctrineTotp);
        $this->entityManager->flush();

        return true;
    }

    public function useBackupCode(string $userId, string $code): bool
    {
        $doctrineTotp = $this->entityManager->getRepository($this->totpClass)
            ->findOneBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        if ($doctrineTotp === null || !$doctrineTotp->isEnabled()) {
            return false;
        }

        $backupCodes = $doctrineTotp->getBackupCodes();

        foreach ($backupCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                unset($backupCodes[$index]);
                $doctrineTotp->setBackupCodes(array_values($backupCodes));
                $this->entityManager->flush();

                return true;
            }
        }

        return false;
    }
}
