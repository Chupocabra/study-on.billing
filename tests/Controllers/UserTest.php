<?php

namespace App\Tests\Controllers;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\AbstractTest;
use Doctrine\Common\Collections\Criteria;
use http\Message;
use Symfony\Component\HttpFoundation\Response;

class UserTest extends AbstractTest
{
    protected function getFixtures(): array
    {
        return [new AppFixtures(
            self::getContainer()->get('security.user_password_hasher')
        )];
    }

    // Авторизация с неправильными данными
    public function testWrongAuthCredentials(): void
    {
        $client = $this->getClient();
        $user = [
            'username' => 'wrongUsername',
            'password' => ''
        ];
        $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($user));
        // Пришел ответ 401
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED, $client->getResponse());
        // Код и сообщение
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Invalid credentials.', $data['message']);
        $this->assertEquals('401', $data['code']);
    }

    // Авторизация
    public function testAuth(): void
    {
        $client = $this->getClient();
        $user = [
            'username' => 'my_user@email.com',
            'password' => 'user'
        ];
        $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($user));
        // Пришел ответ 200
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());
        // Пришел токен
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data['token']);
    }

    // Регистрация с неправильным email
    public function testRegisterWrongEmail(): void
    {
        $client = $this->getClient();
        $user = [
            'username' => 'new_user',
            'password' => 'user'
        ];
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($user)
        );
        // Пришел ответ 400
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST, $client->getResponse());
        // Пришла ошибка с почтой
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Неверный почтовый адрес', $data['errors']['username']);
    }
    // Регистрация с коротким паролем
    public function testRegisterShortPassword(): void
    {
        $client = $this->getClient();
        $user = [
            'username' => 'new_user@mail.com',
            'password' => '111'
        ];
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($user)
        );
        // Пришел ответ 400
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST, $client->getResponse());
        // Пришла ошибка с паролем
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Пароль должен содержать не менее 6 символов', $data['errors']['password']);
    }

    // Регистрация с длинным паролем
    public function testRegisterLongPassword(): void
    {
        $client = $this->getClient();
        $user = [
            'username' => 'new_user@mail.com',
            'password' => '1234567890987654321'
        ];
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($user)
        );
        // Пришел ответ 400
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST, $client->getResponse());
        // Пришла ошибка с паролем
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Пароль должен содержать не более 16 символов', $data['errors']['password']);
    }
    // Регитрация с уже зарегистрированным email
    public function testRegisterNotUniqEmail(): void
    {
        $client = $this->getClient();
        $user = [
            'username' => 'my_user@email.com',
            'password' => '123456'
        ];
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($user)
        );
        // Пришел ответ 409
        $this->assertResponseCode(Response::HTTP_CONFLICT, $client->getResponse());
        // Сообщение об ошибке
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Пользователь с таким email уже зарегистрирован', $data['error']);
    }
    // Регистрация с пустыми полями
    public function testRegisterEmptyFields(): void
    {
        $client = $this->getClient();
        $user = [
            'username' => '',
            'password' => ''
        ];
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($user)
        );
        // Пришел ответ 400
        $this->assertResponseCode(Response::HTTP_BAD_REQUEST, $client->getResponse());
        // Сообщение об ошибке
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Укажите почтовый адрес', $data['errors']['username']);
        $this->assertEquals('Заполните поле с паролем', $data['errors']['password']);
    }
    // Регистрация
    public function testRegister(): void
    {
        $userRepository = self::getContainer()->get(UserRepository::class);
        $countBeforeRegistration = $userRepository->count($criteria = []);
        $client = $this->getClient();
        $user = [
            'username' => 'new_user@email.com',
            'password' => '123456passWord'
        ];
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($user)
        );
        // Пришел ответ 201
        $this->assertResponseCode(Response::HTTP_CREATED, $client->getResponse());
        // Сравнить число user`ов
        $this->assertCount($countBeforeRegistration + 1, $userRepository->findAll());
        // Ответ api
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data['token']);
        $this->assertEquals('ROLE_USER', $data['roles'][0]);
    }
    // Получить текущего пользователя, неавторизован
    public function testGetCurrentUserUnauthorized(): void
    {
        $client = $this->getClient();
        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
        );
        // Пришел ответ 401
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED, $client->getResponse());
        // Ответ api
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('401', $data['code']);
        $this->assertEquals('JWT Token not found', $data['message']);
    }
    // Получить текущего пользователя, неравильный токен
    public function testGetCurrentUserWrongToken(): void
    {
        $client = $this->getClient();
        $token = '123';
        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );
        // Пришел ответ 401
        $this->assertResponseCode(Response::HTTP_UNAUTHORIZED, $client->getResponse());
        // Ответ api
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('401', $data['code']);
        $this->assertEquals('Invalid JWT Token', $data['message']);
    }
    // Получить токен
    private function getToken($user): string
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($user),
        );
        // Ответ api
        $data = json_decode($client->getResponse()->getContent(), true);
        return $data['token'];
    }
    // Получить текущего пользователя
    public function testGetCurrentUser(): void
    {
        $client = $this->getClient();
        $user = [
            'username' => 'my_user@email.com',
            'password' => 'user',
        ];
        $token = $this->getToken($user);
        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token],
        );
        // Пришел ответ 200
        $this->assertResponseCode(Response::HTTP_OK, $client->getResponse());
        // Ответ api
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($user['username'], $data['username']);
        $this->assertNotEmpty($data['roles']);
        $this->assertNotEmpty($data['balance']);
    }
}
