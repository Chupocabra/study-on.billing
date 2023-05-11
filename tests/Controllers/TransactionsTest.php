<?php

namespace App\Tests\Controllers;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\CourseFixtures;
use App\DataFixtures\TransactionsFixtures;
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
    }
    public function testTransactions(): void
    {
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
    }
}
