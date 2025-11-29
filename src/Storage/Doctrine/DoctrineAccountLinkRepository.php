<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\AccountLink as CoreAccountLink;
use BetterAuth\Core\Interfaces\AccountLinkRepositoryInterface;
use BetterAuth\Symfony\Model\AccountLink;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine repository for AccountLink entities.
 *
 * Requires an entity class that extends BetterAuth\Symfony\Model\AccountLink.
 */
final readonly class DoctrineAccountLinkRepository implements AccountLinkRepositoryInterface
{
    /**
     * @param string $accountLinkClass FQCN of entity extending AccountLink
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $accountLinkClass = 'App\\Entity\\AccountLink'
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreAccountLink
    {
        $class = $this->accountLinkClass;
        /** @var AccountLink $link */
        $link = new $class();
        $link->setId($data['id']);
        $link->setUserId($data['user_id']);
        $link->setProvider($data['provider']);
        $link->setProviderId($data['provider_id']);
        $link->setProviderEmail($data['provider_email'] ?? null);
        $link->setIsPrimary($data['is_primary'] ?? false);
        $link->setStatus($data['status'] ?? 'verified');
        $link->setLinkedAt(new DateTimeImmutable($data['linked_at'] ?? 'now'));
        $link->setMetadata($data['metadata'] ?? null);

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        return $this->toCoreEntity($link);
    }

    public function findById(string $id): ?CoreAccountLink
    {
        $link = $this->entityManager->find($this->accountLinkClass, $id);

        return $link ? $this->toCoreEntity($link) : null;
    }

    public function findByUserId(string $userId): array
    {
        $links = $this->entityManager->getRepository($this->accountLinkClass)
            ->findBy(['userId' => $userId], ['linkedAt' => 'DESC']);

        return array_map(fn ($link) => $this->toCoreEntity($link), $links);
    }

    public function findByUserAndProvider(string $userId, string $provider): ?CoreAccountLink
    {
        $link = $this->entityManager->getRepository($this->accountLinkClass)
            ->findOneBy(['userId' => $userId, 'provider' => $provider]);

        return $link ? $this->toCoreEntity($link) : null;
    }

    public function findByProvider(string $provider, string $providerId): ?CoreAccountLink
    {
        $link = $this->entityManager->getRepository($this->accountLinkClass)
            ->findOneBy(['provider' => $provider, 'providerId' => $providerId]);

        return $link ? $this->toCoreEntity($link) : null;
    }

    public function getPrimaryLink(string $userId): ?CoreAccountLink
    {
        $link = $this->entityManager->getRepository($this->accountLinkClass)
            ->findOneBy(['userId' => $userId, 'isPrimary' => true]);

        return $link ? $this->toCoreEntity($link) : null;
    }

    public function isLinked(string $userId, string $provider): bool
    {
        return $this->findByUserAndProvider($userId, $provider) !== null;
    }

    public function countForUser(string $userId): int
    {
        return (int) $this->entityManager->getRepository($this->accountLinkClass)
            ->count(['userId' => $userId]);
    }

    public function setPrimary(string $userId, string $provider): bool
    {
        $this->entityManager->beginTransaction();

        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->update($this->accountLinkClass, 'al')
                ->set('al.isPrimary', ':false')
                ->where('al.userId = :userId')
                ->setParameter('false', false)
                ->setParameter('userId', $userId)
                ->getQuery()
                ->execute();

            /** @var AccountLink|null $link */
            $link = $this->entityManager->getRepository($this->accountLinkClass)
                ->findOneBy(['userId' => $userId, 'provider' => $provider]);

            if ($link === null) {
                $this->entityManager->rollback();

                return false;
            }

            $link->setIsPrimary(true);
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
        /** @var AccountLink|null $link */
        $link = $this->entityManager->find($this->accountLinkClass, $id);
        if ($link === null) {
            return false;
        }

        if (isset($data['provider_email'])) {
            $link->setProviderEmail($data['provider_email']);
        }
        if (isset($data['is_primary'])) {
            $link->setIsPrimary($data['is_primary']);
        }
        if (isset($data['status'])) {
            $link->setStatus($data['status']);
        }
        if (isset($data['metadata'])) {
            $link->setMetadata($data['metadata']);
        }

        $this->entityManager->flush();

        return true;
    }

    public function delete(string $id): bool
    {
        $link = $this->entityManager->find($this->accountLinkClass, $id);
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

        return (int) $qb->delete($this->accountLinkClass, 'al')
            ->where('al.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    private function toCoreEntity(AccountLink $link): CoreAccountLink
    {
        return CoreAccountLink::fromArray([
            'id' => (string) $link->getId(),
            'user_id' => (string) $link->getUserId(),
            'provider' => $link->getProvider(),
            'provider_id' => $link->getProviderId(),
            'provider_email' => $link->getProviderEmail(),
            'is_primary' => $link->isPrimary(),
            'status' => $link->getStatus(),
            'linked_at' => $link->getLinkedAt()->format('Y-m-d H:i:s'),
            'metadata' => $link->getMetadata(),
        ]);
    }
}
