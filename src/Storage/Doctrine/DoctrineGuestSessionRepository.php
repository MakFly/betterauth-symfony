<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\GuestSession as CoreGuestSession;
use BetterAuth\Core\Interfaces\GuestSessionRepositoryInterface;
use BetterAuth\Symfony\Model\GuestSession;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineGuestSessionRepository implements GuestSessionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreGuestSession
    {
        $session = new GuestSession();
        $session->id = $data['id'];
        $session->token = $data['token'];
        $session->deviceInfo = $data['device_info'] ?? null;
        $session->ipAddress = $data['ip_address'] ?? null;
        $session->createdAt = new DateTimeImmutable($data['created_at'] ?? 'now');
        $session->expiresAt = new DateTimeImmutable($data['expires_at']);
        $session->metadata = $data['metadata'] ?? null;

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $this->toCoreEntity($session);
    }

    public function findById(string $id): ?CoreGuestSession
    {
        $session = $this->entityManager->find(GuestSession::class, $id);

        return $session ? $this->toCoreEntity($session) : null;
    }

    public function findByToken(string $token): ?CoreGuestSession
    {
        $session = $this->entityManager->getRepository(GuestSession::class)
            ->findOneBy(['token' => $token]);

        return $session ? $this->toCoreEntity($session) : null;
    }

    public function delete(string $id): bool
    {
        $session = $this->entityManager->find(GuestSession::class, $id);
        if ($session === null) {
            return false;
        }

        $this->entityManager->remove($session);
        $this->entityManager->flush();

        return true;
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        return $qb->delete(GuestSession::class, 'gs')
            ->where('gs.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    private function toCoreEntity(GuestSession $session): CoreGuestSession
    {
        return CoreGuestSession::fromArray([
            'id' => $session->id,
            'token' => $session->token,
            'device_info' => $session->deviceInfo,
            'ip_address' => $session->ipAddress,
            'created_at' => $session->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $session->expiresAt->format('Y-m-d H:i:s'),
            'metadata' => $session->metadata,
        ]);
    }
}
