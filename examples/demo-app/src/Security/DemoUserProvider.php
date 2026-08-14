<?php

declare(strict_types=1);

namespace Demo\Security;

use Demo\Entity\DemoAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<DemoAccount> */
final readonly class DemoUserProvider implements UserProviderInterface
{
    public function __construct(private EntityManagerInterface $entities)
    {
    }

    public function loadUserByIdentifier(string $identifier): DemoAccount
    {
        $account = $this->entities->find(DemoAccount::class, $identifier);
        if (!$account instanceof DemoAccount) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $account;
    }

    public function refreshUser(UserInterface $user): DemoAccount
    {
        if (!$user instanceof DemoAccount) {
            throw new \InvalidArgumentException('Unsupported user.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === DemoAccount::class;
    }
}
