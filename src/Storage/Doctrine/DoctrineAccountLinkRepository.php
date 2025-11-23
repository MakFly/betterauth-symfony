<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\AccountLink as CoreAccountLink;
use BetterAuth\Core\Interfaces\AccountLinkRepositoryInterface;
use BetterAuth\Symfony\Model\AccountLink;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAccountLinkRepository implements AccountLinkRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreAccountLink
    {
        $link = new AccountLink();
        $link->id = $data['id'];
        $link->userId = $data['user_id'];
        $link->provider = $data['provider'];
        $link->providerId = $data['provider_id'];
        $link->providerEmail = $data['provider_email'] ?? null;
        $link->isPrimary = $data['is_primary'] ?? false;
        $link->status = $data['status'] ?? 'verified';
        $link->linkedAt = new DateTimeImmutable($data['linked_at'] ?? 'now');
        $link->metadata = $data['metadata'] ?? null;

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        return $this->toCoreEntity($link);
    }

    public function findById(string $id): ?CoreAccountLink
    {
        $link = $this->entityManager->find(AccountLink::class, $id);

        return $link ? $this->toCoreEntity($link) : null;
    }

    public function findByUserId(string $userId): array
    {
        $links = $this->entityManager->getRepository(AccountLink::class)
            ->findBy(['userId' => $userId], ['linkedAt' => 'DESC']);

        return array_map(fn ($link) => $this->toCoreEntity($link), $links);
    }

    public function findByUserAndProvider(string $userId, string $provider): ?CoreAccountLink
    {
        $link = $this->entityManager->getRepository(AccountLink::class)
            ->findOneBy(['userId' => $userId, 'provider' => $provider]);

        return $link ? $this->toCoreEntity($link) : null;
    }

    public function findByProvider(string $provider, string $providerId): ?CoreAccountLink
    {
        $link = $this->entityManager->getRepository(AccountLink::class)
            ->findOneBy(['provider' => $provider, 'providerId' => $providerId]);

        return $link ? $this->toCoreEntity($link) : null;
    }

    public function getPrimaryLink(string $userId): ?CoreAccountLink
    {
        $link = $this->entityManager->getRepository(AccountLink::class)
            ->findOneBy(['userId' => $userId, 'isPrimary' => true]);

        return $link ? $this->toCoreEntity($link) : null;
    }

    public function isLinked(string $userId, string $provider): bool
    {
        return $this->findByUserAndProvider($userId, $provider) !== null;
    }

    public function countForUser(string $userId): int
    {
        return (int) $this->entityManager->getRepository(AccountLink::class)
            ->count(['userId' => $userId]);
    }

    public function setPrimary(string $userId, string $provider): bool
    {
        $this->entityManager->beginTransaction();

        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->update(AccountLink::class, 'al')
                ->set('al.isPrimary', ':false')
                ->where('al.userId = :userId')
                ->setParameter('false', false)
                ->setParameter('userId', $userId)
                ->getQuery()
                ->execute();

            $link = $this->entityManager->getRepository(AccountLink::class)
                ->findOneBy(['userId' => $userId, 'provider' => $provider]);

            if ($link === null) {
                $this->entityManager->rollback();

                return false;
            }

            $link->isPrimary = true;
            $this->entityManager->flush();
            $this->entityManager->commit();

            return true;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function update(string $id, array $data): bool
    {
        $link = $this->entityManager->find(AccountLink::class, $id);
        if ($link === null) {
            return false;
        }

        if (isset($data['provider_email'])) {
            $link->providerEmail = $data['provider_email'];
        }
        if (isset($data['is_primary'])) {
            $link->isPrimary = $data['is_primary'];
        }
        if (isset($data['status'])) {
            $link->status = $data['status'];
        }
        if (isset($data['metadata'])) {
            $link->metadata = $data['metadata'];
        }

        $this->entityManager->flush();

        return true;
    }

    public function delete(string $id): bool
    {
        $link = $this->entityManager->find(AccountLink::class, $id);
        if ($link === null) {
            return false;
        }

        $this->entityManager->remove($link);
        $this->entityManager->flush();

        return true;
    }

    public function deleteAllForUser(string $userId): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        return (int) $qb->delete(AccountLink::class, 'al')
            ->where('al.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    private function toCoreEntity(AccountLink $link): CoreAccountLink
    {
        return CoreAccountLink::fromArray([
            'id' => $link->id,
            'user_id' => $link->userId,
            'provider' => $link->provider,
            'provider_id' => $link->providerId,
            'provider_email' => $link->providerEmail,
            'is_primary' => $link->isPrimary,
            'status' => $link->status,
            'linked_at' => $link->linkedAt->format('Y-m-d H:i:s'),
            'metadata' => $link->metadata,
        ]);
    }
}
