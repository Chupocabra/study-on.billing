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

    private function addCourse(string $token, string $data): Response
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/courses',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            $data
        );
        return $client->getResponse();
    }
    // test adding
    public function testAdding()
    {
        $courseRepo = $this
            ->getEntityManager()
            ->getRepository(Course::class);
        $countCoursesBeforeAdding = count($courseRepo->findAll());
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => 'course-code',
            'price' => 999.99,
        ];
        $response = $this->addCourse($token, json_encode($data));
        $this->assertResponseCode(Response::HTTP_CREATED, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame($responseData['success'], true);
        $this->assertCount($countCoursesBeforeAdding + 1, $courseRepo->findAll());
        $this->assertNotNull($courseRepo->findOneBy(['code' => $data['code']]));
        $this->assertSame($data['title'], $courseRepo->findOneBy(['code' => $data['code']])->getTitle());
    }
    public function testAddingUnauthorized()
    {
        $courseRepo = $this
            ->getEntityManager()
            ->getRepository(Course::class);
        $countCoursesBeforeAdding = count($courseRepo->findAll());
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => 'course-code',
            'price' => 999.99,
        ];
        $response = $this->addCourse('123', json_encode($data));
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame($responseData['message'], 'Invalid JWT Token');
        $this->assertCount($countCoursesBeforeAdding, $courseRepo->findAll());
    }
    public function testAddingByUser()
    {
        $courseRepo = $this
            ->getEntityManager()
            ->getRepository(Course::class);
        $countCoursesBeforeAdding = count($courseRepo->findAll());
        $token = $this->getToken([
            'username' => 'my_user@email.com',
            'password' => 'user'
        ]);
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => 'course-code',
            'price' => 999.99,
        ];
        $response = $this->addCourse($token, json_encode($data));
        $this->assertResponseCode(Response::HTTP_FORBIDDEN, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame($responseData['success'], false);
        $this->assertSame($responseData['message'], 'У вас недостаточно прав.');
        $this->assertCount($countCoursesBeforeAdding, $courseRepo->findAll());
    }
    public function testAddingNotUniqueCode()
    {
        $courseRepo = $this
            ->getEntityManager()
            ->getRepository(Course::class);
        $countCoursesBeforeAdding = count($courseRepo->findAll());
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => 'java-dev',
            'price' => 999.99,
        ];
        $response = $this->addCourse($token, json_encode($data));
        $this->assertResponseCode(Response::HTTP_CONFLICT, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame($responseData['success'], false);
        $this->assertSame(
            $responseData['message'],
            'Код курса должен быть уникален. Курс с таким кодом уже существует.'
        );
        $this->assertCount($countCoursesBeforeAdding, $courseRepo->findAll());
    }
    public function testAddingNotCorrectData()
    {
        $courseRepo = $this
            ->getEntityManager()
            ->getRepository(Course::class);
        $countCoursesBeforeAdding = count($courseRepo->findAll());
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $response = $this->addCourse($token, json_encode([]));
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame($responseData['success'], false);
        $this->assertSame(
            $responseData['message'],
            [
                'code' => 'Укажите код курса',
                'type' => 'Укажите тип курса',
                'price' => 'Укажите стоимость курса',
                'title' => 'Укажите название курса',
            ]
        );
        $this->assertCount($countCoursesBeforeAdding, $courseRepo->findAll());
    }
    private function editCourse(string $token, string $code, string $data): Response
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            "/api/v1/courses/$code",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            $data
        );
        return $client->getResponse();
    }
    // test editing
    public function testEditing()
    {
        $courseRepo = $this->getEntityManager()->getRepository(Course::class);
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $course = $courseRepo->findAll()[0];
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => 'course-code',
            'price' => 999.99,
        ];
        $response = $this->editCourse($token, $course->getCode(), json_encode($data));
        $this->assertResponseCode(Response::HTTP_OK, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertNull($courseRepo->findOneBy(['code' => $course->getCode()]));
        $this->assertSame($responseData['success'], true);
        $this->assertSame($courseRepo->findOneBy(['code' => $data['code']])->getTitle(), $data['title']);
    }
    public function testEditingNotAuthorized()
    {
        $courseRepo = $this->getEntityManager()->getRepository(Course::class);
        $course = $courseRepo->findAll()[0];
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => 'course-code',
            'price' => 999.99,
        ];
        $response = $this->editCourse('', $course->getCode(), json_encode($data));
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertNotNull($courseRepo->findOneBy(['code' => $course->getCode()]));
        $this->assertSame($responseData['message'], 'JWT Token not found');
    }
    public function testEditingByUser()
    {
        $courseRepo = $this->getEntityManager()->getRepository(Course::class);
        $course = $courseRepo->findAll()[0];
        $token = $this->getToken([
            'username' => 'my_user@email.com',
            'password' => 'user'
        ]);
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => 'course-code',
            'price' => 999.99,
        ];
        $response = $this->editCourse($token, $course->getCode(), json_encode($data));
        $this->assertResponseCode(Response::HTTP_FORBIDDEN, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertNotNull($courseRepo->findOneBy(['code' => $course->getCode()]));
        $this->assertSame($responseData['message'], 'У вас недостаточно прав.');
    }
    public function testEditingCourseNotFound()
    {
        $courseRepo = $this->getEntityManager()->getRepository(Course::class);
        $course = '22';
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => 'course-code',
            'price' => 999.99,
        ];
        $response = $this->editCourse($token, $course, json_encode($data));
        $this->assertResponseCode(Response::HTTP_NOT_FOUND, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame($responseData['message'], 'Курс с кодом 22 не найден.');
        $this->assertNull($courseRepo->findOneBy(['code' => $course]));
    }
    public function testEditingNewCodeAlreadyExists()
    {
        $courseRepo = $this->getEntityManager()->getRepository(Course::class);
        $course1 = $courseRepo->findAll()[0];
        $course2 = $courseRepo->findAll()[1];
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $data = [
            'type' => 'buy',
            'title' => 'Course Title',
            'code' => $course2->getCode(),
            'price' => 999.99,
        ];
        $response = $this->editCourse($token, $course1->getCode(), json_encode($data));
        $this->assertResponseCode(Response::HTTP_CONFLICT, $response);
        $responseData = json_decode($response->getContent(), true);
        $course2Code = $course2->getCode();
        $this->assertSame(
            $responseData['message'],
            "Курс с кодом $course2Code уже существует."
        );
    }
    public function testEditingWrongData()
    {
        $courseRepo = $this->getEntityManager()->getRepository(Course::class);
        $course = $courseRepo->findAll()[0];
        $token = $this->getToken([
            'username' => 'my_admin@email.com',
            'password' => 'admin'
        ]);
        $response = $this->editCourse($token, $course->getCode(), json_encode([]));
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST, $response);
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame($responseData['success'], false);
        $this->assertSame($responseData['message'], [
            'code' => 'Укажите код курса',
            'type' => 'Укажите тип курса',
            'price' => 'Укажите стоимость курса',
            'title' => 'Укажите название курса',
        ]);
    }
}
