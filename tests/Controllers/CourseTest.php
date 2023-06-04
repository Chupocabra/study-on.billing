<?php

namespace App\Tests\Controllers;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\CourseFixtures;
use App\DataFixtures\TransactionsFixtures;
use App\Entity\Course;
use App\Service\PaymentService;
use App\Tests\AbstractTest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CourseTest extends AbstractTest
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

    public function testGetCourses(): void
    {
        $client = $this->getClient();
        $client->request(
            'GET',
            '/api/v1/courses',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
        );
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());
        $this->assertCount(5, $data);
    }

    public function testCourseNotFound(): void
    {
        $client = $this->getClient();
        $client->request(
            'GET',
            '/api/v1/courses/22',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
        );
        $this->assertResponseCode(Response::HTTP_NOT_FOUND, $client->getResponse());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($data['message'], 'Курс с кодом 22 не найден.');
    }

    // Проверяет все курсы
    public function testGetCourse(): void
    {
        $client = $this->getClient();
        $courses = self::getEntityManager()->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            $code = $course->getCode();
            $client->request(
                'GET',
                "/api/v1/courses/$code",
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
            );
            $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());
            $data = json_decode($client->getResponse()->getContent(), true);
            $this->assertSame($data['code'], $course->getCode());
            $this->assertSame($data['type'], $course->getType());
        }
    }

    public function testNotEnoughMoney(): void
    {
        $response = $this->paymentReq(
            $this->getToken([
                'username' => 'my_admin@email.com',
                'password' => 'admin'
            ]),
            'php-dev',
        );
        $this->assertResponseCode(Response::HTTP_NOT_ACCEPTABLE, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertSame($data['message'], 'На вашем счету недостаточно средств.');
    }

    public function testNotAuthorized(): void
    {
        $response = $this->paymentReq(
            '123',
            'php-dev',
        );
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertSame($data['message'], 'Invalid JWT Token');
    }

    public function testPayCourse(): void
    {
        $course = self::getEntityManager()
            ->getRepository(Course::class)
            ->findOneBy(['code' => 'python-dev']);
        $response = $this->paymentReq(
            $this->getToken([
                'username' => 'my_admin@email.com',
                'password' => 'admin'
            ]),
            'python-dev',
        );
        $this->assertResponseCode(Response::HTTP_OK, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertSame($data['success'], true);
        $this->assertNotEmpty($data['course_type']);
        $this->assertSame($data['course_type'], $course->getType());
    }

    private function paymentReq(string $token, string $code): Response
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/courses/' . $code . '/pay',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
        );
        return $client->getResponse();
    }
}
