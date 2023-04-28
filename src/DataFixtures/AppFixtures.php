<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // user
        $user = new User();
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            'user'
        );
        $user->setRoles(['ROLE_USER'])
            ->setEmail('my_user@email.com')
            ->setPassword($hashedPassword)
            ->setBalance($_ENV['USER_BALANCE']);
        $manager->persist($user);

        // admin
        $user = new User();
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            'admin'
        );
        $user->setRoles(['ROLE_SUPER_ADMIN'])
            ->setEmail('my_admin@email.com')
            ->setPassword($hashedPassword)
            ->setBalance($_ENV['USER_BALANCE']);
        $manager->persist($user);

        $manager->flush();
    }
}
