<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 *
 * @method Transaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function add(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Transaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findFilteredTransactions(User $user, array $filters)
    {
        $query = $this->createQueryBuilder('t')
            ->leftJoin('t.course', 'c')
            ->andWhere('t.client = :user')
            ->setParameter('user', $user->getId())
            ->orderBy('t.date_time', 'DESC')
        ;
        if (!is_null($filters['type'])) {
            $query->andWhere('t.type = :type')->setParameter('type', $filters['type']);
        }
        if (!is_null($filters['course_code'])) {
            $query->andWhere('c.code = :code')->setParameter('code', $filters['course_code']);
        }
        if (!is_null($filters['skip_expired'])) {
            $query->andWhere('t.expire IS NULL OR t.expire > :now')->setParameter('now', new \DateTimeImmutable());
        }
        return $query->getQuery()->getResult();
    }

    public function findExpiredTransactions(User $user)
    {
        $start = new \DateTimeImmutable();
        $end = $start->add(new \DateInterval('P1D'));
        $query = $this->createQueryBuilder('t')
            ->select('c.title as title', 't.expire as expire')
            ->join('t.course', 'c')
            ->andWhere('t.client = :user')
            ->setParameter('user', $user->getId())
            ->andWhere('c.type = 1')
            ->andWhere('t.expire BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('t.date_time', 'DESC');
        return $query->getQuery()->getResult();
    }

    public function findTransactionsForReport(\DateTimeImmutable $start, \DateTimeImmutable $end)
    {
        $query = 'SELECT c.title as title, c.type as type, count(t.id) as count, sum(t.value) as total 
                    FROM App\Entity\Transaction t, App\Entity\Course c
                    WHERE c.type <> 2 AND t.course = c.id AND t.date_time BETWEEN :start AND :end
                    GROUP BY c.title, c.type';
        return $this
            ->getEntityManager()
            ->createQuery($query)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getResult();
    }

//    /**
//     * @return Transaction[] Returns an array of Transaction objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
