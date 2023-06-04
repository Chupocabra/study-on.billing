<?php

namespace App\Tests\Controllers;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\CourseFixtures;
use App\DataFixtures\TransactionsFixtures;
use App\Entity\User;
use App\Service\PaymentService;
use App\Tests\AbstractTest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TransactionsTest extends AbstractTest
{
    protected function getFixtures(): array
    {
        return [
            new AppFixtures(
                self::getContainer()->get(UserPasswordHasherInterface::class),
                self::getContainer()->get(PaymentService::class)
            ),
            new CourseFixtures(),
            new TransactionsFixtures(),
        ];
    }
    public function testToken()
    {
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $this->assertCount(3, explode('.', $token));
    }
    private function getToken($user): string
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($user)
        );
        return json_decode($client->getResponse()->getContent(), true)['token'];
    }
    public function testTransactionsNotAuthorized(): void
    {
        $token = 'invalid';
        $client = $this->getClient();
        $client->request(
            'GET',
            '/api/v1/transactions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
        );
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED, $client->getResponse());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($data['message'], 'Invalid JWT Token');
    }
    public function testTransactions(): void
    {
        $userTransactions = count(self::getEntityManager()
            ->getRepository(User::class)
            ->findOneBy(['email' => 'my_admin@email.com'])
            ->getTransactions());
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $client = $this->getClient();
        $client->request(
            'GET',
            '/api/v1/transactions',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ],
        );
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());
        $this->assertCount($userTransactions, json_decode($client->getResponse()->getContent(), true));
    }
    // DONE: transactions_refreshed
    public function testTransactionsRefreshed()
    {
        $oldUserTransactions = count(self::getEntityManager()
            ->getRepository(User::class)
            ->findOneBy(['email' => 'my_user@email.com'])
            ->getTransactions());
        $client = $this->getClient();
        $client->request(
            'GET',
            '/api/v1/transactions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getToken([
                        'username' => 'my_user@email.com',
                        'password' => 'user'
                    ]),
            ],
        );
        $this->assertCount($oldUserTransactions, json_decode($client->getResponse()->getContent(), true));
        $courseCode = 'data-analyst';
        $client->request(
            'POST',
            '/api/v1/courses/' . $courseCode . '/pay',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->getToken([
                        'username' => 'my_user@email.com',
                        'password' => 'user'
                    ]),
            ],
        );
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());
        $userTransactions = self::getEntityManager()
            ->getRepository(User::class)
            ->findOneBy(['email' => 'my_user@email.com'])
            ->getTransactions();
        $this->assertCount($oldUserTransactions + 1, $userTransactions);
    }
}
