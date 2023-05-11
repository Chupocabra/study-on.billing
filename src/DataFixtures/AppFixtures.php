<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture implements OrderedFixtureInterface
{
    private UserPasswordHasherInterface $passwordHasher;
    private PaymentService $paymentService;

    public function __construct(UserPasswordHasherInterface $passwordHasher, PaymentService $paymentService)
    {
        $this->passwordHasher = $passwordHasher;
        $this->paymentService = $paymentService;
    }

    /**
     * @throws Exception
     */
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
            ->setBalance(0);
        $manager->persist($user);
        // пополняем баланс
        $this->paymentService->deposit($_ENV['USER_BALANCE'], $user);

        // admin
        $user = new User();
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            'admin'
        );
        $user->setRoles(['ROLE_SUPER_ADMIN'])
            ->setEmail('my_admin@email.com')
            ->setPassword($hashedPassword)
            ->setBalance(0);
        $manager->persist($user);
        // пополняем баланс
        $this->paymentService->deposit($_ENV['USER_BALANCE'], $user);
        $manager->flush();
    }

    public function getOrder(): int
    {
        return 1;
    }
}
