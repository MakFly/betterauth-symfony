<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\App\DataFixtures;

use BetterAuth\Symfony\Tests\App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test fixtures: pre-creates users for functional tests.
 */
class UserFixtures extends Fixture
{
    public const USER_UUID = '01929fa2-b55e-7000-8000-000000000001';
    public const USER_EMAIL = 'user@example.com';
    public const USER_PASSWORD = 'password123';

    public const ADMIN_UUID = '01929fa2-b55e-7000-8000-000000000002';
    public const ADMIN_EMAIL = 'admin@example.com';
    public const ADMIN_PASSWORD = 'admin-password123';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setId(self::USER_UUID);
        $user->setEmail(self::USER_EMAIL);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::USER_PASSWORD));

        $admin = new User();
        $admin->setId(self::ADMIN_UUID);
        $admin->setEmail(self::ADMIN_EMAIL);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, self::ADMIN_PASSWORD));
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $admin->setEmailVerified(true);

        $manager->persist($user);
        $manager->persist($admin);
        $manager->flush();

        $this->addReference('user', $user);
        $this->addReference('admin', $admin);
    }
}
