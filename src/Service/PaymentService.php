<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class PaymentService
{
    private const DEPOSIT = 2;
    private const PAYMENT = 1;
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @throws Exception
     */
    public function payment(User $user, Course $course): Transaction
    {
        $this->entityManager->getConnection()->beginTransaction();
        try {
            if ($user->getBalance() < $course->getPrice()) {
                throw new Exception('На вашем счету недостаточно средств.', Response::HTTP_NOT_ACCEPTABLE);
            }
            $transaction = new Transaction();
            $transaction
                ->setClient($user)
                ->setType(self::PAYMENT)
                ->setValue($course->getPrice())
                ->setCourse($course)
                ->setDateTime(new \DateTimeImmutable());
            if ($course->getType() === 'rent') {
                $transaction->setExpire((new DateTimeImmutable())->add(new DateInterval('P1W')));
            }
            $user->setBalance($user->getBalance() - $course->getPrice());
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode());
        }
        return $transaction;
    }

    /**
     * @throws Exception
     */
    public function deposit(float $sum, User $user)
    {
        $this->entityManager->getConnection()->beginTransaction();
        try {
            $transaction = new Transaction();
            $transaction
                ->setClient($user)
                ->setType(self::DEPOSIT)
                ->setValue($sum)
                ->setDateTime(new \DateTimeImmutable());
            $user->setBalance($user->getBalance() + $sum);
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
}
