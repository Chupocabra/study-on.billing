<?php

namespace App\Controller;

use App\Entity\User;
use App\DTO\UserDTO;
use App\Repository\UserRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

/**
 * @Route("/api/v1")
 */
class UserController extends AbstractController
{
    private Serializer $serializer;

    private ValidatorInterface $validator;

    private UserPasswordHasherInterface $passwordHasher;

    private PaymentService $paymentService;

    public function __construct(
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        PaymentService $paymentService
    ) {
        $this->serializer = SerializerBuilder::create()->build();
        $this->validator = $validator;
        $this->passwordHasher = $passwordHasher;
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\POST(
     *   path="/auth",
     *   summary="Вход",
     *   description="Логин с помощью email и пароль",
     * )
     * @OA\RequestBody(
     *   required=true,
     *   description="Введите данные пользователя",
     *   @OA\JsonContent(
     *     required={"username", "password"},
     *     @OA\Property(property="username", type="string", format="email", example="user@email.com"),
     *     @OA\Property(property="password", type="string", format="password", example="PassWord1234"),
     *     )
     *   )
     * )
     * @OA\Response(
     *     response=200,
     *     description="Авторизация",
     *     @OA\JsonContent(
     *       @OA\Property(property="token", type="string"),
     *       @OA\Property(property="refresh_token", type="string")
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Введены неверные данные",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="401"),
     *       @OA\Property(property="message", type="string", example="Invalid credentials.")
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Ошибка",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="400"),
     *       @OA\Property(property="message", type="string", example="Error")
     *     )
     * )
     * @OA\Tag(name="User")
     * @Route("/auth", name="api_auth", methods={"POST"})
     */
    public function auth(): JsonResponse
    {
        return new JsonResponse([]);
    }

    /**
     * @OA\Post(
     *   path="/register",
     *   summary="Регистрация",
     *   description="Регистрация с помощью email и password",
     * )
     * @OA\RequestBody(
     *   required=true,
     *   description="Введите данные пользователя",
     *   @OA\JsonContent(
     *     required={"username", "password"},
     *     @OA\Property(property="username", type="string", format="email", example="user@email.com"),
     *     @OA\Property(property="password", type="string", format="password", example="PassWord1234"),
     *   )
     * )
     * @OA\Response(
     *     response=201,
     *     description="Регистрация",
     *     @OA\JsonContent(
     *       @OA\Property(property="token", type="string"),
     *       @OA\Property(property="refresh_token", type="string"),
     *       @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *     )
     * )
     * @OA\Response(
     *     response=409,
     *     description="Email уже зарегистрирован",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="409"),
     *       @OA\Property(property="message", type="string",
     *     example="Пользователь с таким email уже существует"),
     *     )
     * )
     * @OA\Response(
     *     response=400,
     *     description="Ошибки валидации",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="400"),
     *       @OA\Property(property="errors", type="array",
     *         @OA\Items(@OA\Property(type="string", property="error"))
     *     ),
     * )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Ошибка",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="400"),
     *       @OA\Property(property="message", type="string", example="Error")
     *     )
     * )
     * @OA\Tag(name="User")
     * @Route("/register", name="api_register", methods={"POST"})
     * @throws Exception
     */
    public function register(
        Request $request,
        UserRepository $userRepository,
        JWTTokenManagerInterface $JWTManager,
        RefreshTokenManagerInterface $refreshTokenManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator
    ): JsonResponse {
        $dto = $this->serializer->deserialize($request->getContent(), UserDTO::class, 'json');
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $errors_json = [];
            foreach ($errors as $error) {
                $errors_json[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse([
                'code' => '400',
                'errors' => $errors_json
            ], Response::HTTP_BAD_REQUEST);
        }
        if ($userRepository->findOneBy(['email' => $dto->username])) {
            return new JsonResponse([
                'code' => '409',
                'message' => 'Пользователь с таким email уже зарегистрирован'
            ], Response::HTTP_CONFLICT);
        }
        $user = User::fromDto($dto);
        $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
        $userRepository->add($user, true);
        $this->paymentService->deposit($_ENV['USER_BALANCE'], $user);


        $refreshToken = $refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $refreshTokenManager->save($refreshToken);

        return new JsonResponse([
            'token' => $JWTManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *   path="/users/current",
     *   summary="Текущий пользователь",
     *   description="Получить токен текущего пользователя",
     * )
     * @OA\Response(
     *     response=200,
     *     description="Текущий пользователь",
     *     @OA\JsonContent(
     *       @OA\Property(property="username", type="string"),
     *       @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *       @OA\Property(property="balance", type="float"),
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Ошибка",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="400"),
     *       @OA\Property(property="message", type="string", example="Error")
     *     )
     * )
     * @Route("/users/current", name="api_get_current", methods={"GET"})
     * @OA\Tag(name="User")
     * @Security(name="Bearer")
     */
    public function getCurrent(): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse([
                "code" => '401',
                "message" => "Пользователь не авторизован"
            ], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'username' => $this->getUser()->getUserIdentifier(),
            'roles' => $this->getUser()->getRoles(),
            'balance' => $this->getUser()->getBalance(),
        ], Response::HTTP_OK);
    }

    /**
     * @OA\Post(
     *   path="/token/refresh",
     *   summary="Обновить JWT",
     *   description="Обновление JWT",
     * )
     * @OA\RequestBody(
     *   required=true,
     *   @OA\JsonContent(
     *     @OA\Property(property="refresh_token", type="string", example="t0k3n"),
     *   )
     * )
     * @OA\Response(
     *     response=200,
     *     description="Обновление",
     *     @OA\JsonContent(
     *       @OA\Property(property="token", type="string"),
     *       @OA\Property(property="refresh_token", type="string"),
     *     )
     * )
     * @OA\Response(
     *     response="401",
     *     description="Ошибка аутентификации",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="401"),
     *       @OA\Property(property="message", type="string", example="Invalid credentials.")
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Ошибка",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="400"),
     *       @OA\Property(property="message", type="string", example="Error")
     *     )
     * )
     * @OA\Tag(name="User")
     * @Route("/token/refresh", name="api_refresh_token", methods={"POST"})
     */
    public function refresh()
    {
    }
}
