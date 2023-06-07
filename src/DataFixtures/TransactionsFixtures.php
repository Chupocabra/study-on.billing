<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TransactionsFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $userRepository = $manager->getRepository(User::class);
        $courseRepository = $manager->getRepository(Course::class);

        $user = $userRepository->findOneBy(['email' => 'my_user@email.com']);
        $admin = $userRepository->findOneBy(['email' => 'my_admin@email.com']);
        // type 1 -- payment
        // type 2 -- deposit
        $transactions = [
            // арендовал data-analyst
            [
                'client' => $user,
                'type' => 1,
                'value' => $courseRepository->findOneBy(['code' => 'data-analyst'])->getPrice(),
                'date_time' => (new \DateTimeImmutable())->sub(new \DateInterval('P1M10D')),
                'expire' => (new \DateTimeImmutable())->sub(new \DateInterval('P1M3D')),
                'course' => $courseRepository->findOneBy(['code' => 'data-analyst'])
            ],
            // пополнил
            [
                'client' => $user,
                'type' => 2,
                'value' => 600,
                'date_time' => (new \DateTimeImmutable())->sub(new \DateInterval('P1M2D')),
            ],
            // арендовал еще раз
            [
                'client' => $user,
                'type' => 1,
                'value' => $courseRepository->findOneBy(['code' => 'data-analyst'])->getPrice(),
                'date_time' => (new \DateTimeImmutable())->sub(new \DateInterval('P1M2D')),
                'expire' => (new \DateTimeImmutable())->sub(new \DateInterval('P27D')),
                'course' => $courseRepository->findOneBy(['code' => 'data-analyst'])
            ],
            // пополнил
            [
                'client' => $user,
                'type' => 2,
                'value' => 4000,
                'date_time' => (new \DateTimeImmutable())->sub(new \DateInterval('P1M')),
            ],
            // арендовал недавно
            [
                'client' => $user,
                'type' => 1,
                'value' => $courseRepository->findOneBy(['code' => 'python-dev'])->getPrice(),
                'date_time' => new \DateTimeImmutable(),
                'expire' => (new \DateTimeImmutable())->add(new \DateInterval('P10D')),
                'course' => $courseRepository->findOneBy(['code' => 'python-dev'])
            ],
            // купил
            [
                'client' => $user,
                'type' => 1,
                'value' => $courseRepository->findOneBy(['code' => 'java-dev'])->getPrice(),
                'date_time' => (new \DateTimeImmutable())->sub(new \DateInterval('P8D')),
                'course' => $courseRepository->findOneBy(['code' => 'java-dev'])
            ],
            // newTransactions
            // пополнил
            [
                'client' => $user,
                'type' => 2,
                'value' => 1000,
                'date_time' => new \DateTimeImmutable(),
            ],
            // арендовал
            [
                'client' => $user,
                'type' => 1,
                'value' => $courseRepository->findOneBy(['code' => 'python-dev'])->getPrice(),
                'date_time' => new \DateTimeImmutable(),
                'expire' => (new \DateTimeImmutable())->add(new \DateInterval('P7D')),
                'course' => $courseRepository->findOneBy(['code' => 'python-dev'])
            ],
            // пополнил
            [
                'client' => $admin,
                'type' => 2,
                'value' => 1000,
                'date_time' => new \DateTimeImmutable(),
            ],
            // арендовал
            [
                'client' => $admin,
                'type' => 1,
                'value' => $courseRepository->findOneBy(['code' => 'python-dev'])->getPrice(),
                'date_time' => new \DateTimeImmutable(),
                'expire' => (new \DateTimeImmutable())->add(new \DateInterval('P7D')),
                'course' => $courseRepository->findOneBy(['code' => 'python-dev'])
            ],
            // купил
            [
                'client' => $admin,
                'type' => 1,
                'value' => $courseRepository->findOneBy(['code' => 'java-dev'])->getPrice(),
                'date_time' => new \DateTimeImmutable(),
                'course' => $courseRepository->findOneBy(['code' => 'java-dev'])
            ],
            // арендовал
            [
                'client' => $admin,
                'type' => 1,
                'value' => $courseRepository->findOneBy(['code' => 'data-analyst'])->getPrice(),
                'date_time' => new \DateTimeImmutable(),
                'expire' => (new \DateTimeImmutable())->add(new \DateInterval('P7D')),
                'course' => $courseRepository->findOneBy(['code' => 'data-analyst'])
            ],
        ];
        foreach ($transactions as $t) {
            $transaction = new Transaction();
            $transaction
                ->setClient($t['client'])
                ->setType($t['type'])
                ->setValue($t['value'])
                ->setDateTime($t['date_time']);
            if (isset($t['expire'])) {
                $transaction->setExpire($t['expire']);
            }
            if (isset($t['course'])) {
                $transaction->setCourse($t['course']);
            }
            $manager->persist($transaction);
        }
        $manager->flush();
    }
    public function getOrder(): int
    {
        return 3;
    }
}
