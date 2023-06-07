<?php

namespace App\Controller;

use App\DTO\CourseDTO;
use App\Entity\Course;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @Route("/api/v1/courses")
 */
class CoursesController extends AbstractController
{
    private CourseRepository $courseRepository;
    private Serializer $serializer;
    private ValidatorInterface $validator;

    public function __construct(CourseRepository $courseRepository, ValidatorInterface $validator)
    {
        $this->courseRepository = $courseRepository;
        $this->serializer = SerializerBuilder::create()->build();
        $this->validator = $validator;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/courses",
     *     summary="Список курсов",
     *     description="Список курсов",
     * )
     * @OA\Response(
     *     response="200",
     *     description="Возвращает список всех курсов",
     *     @OA\JsonContent(
     *       type="array",
     *       @OA\Items(
     *          @OA\Property(property="code", type="string", example="code-course-name"),
     *          @OA\Property(property="type", type="string", example="rent"),
     *          @OA\Property(property="price", type="float", example="99.90"),
     *       )
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Ошибка",
     *     @OA\JsonContent(
     *          @OA\Property(property="code", type="string", example="400"),
     *          @OA\Property(property="message", type="string", example="Error"),
     *     )
     * )
     * @OA\Tag(name="Course")
     * @Route("", name="app_courses", methods={"GET"})
     */
    public function getCourses(): JsonResponse
    {
        $courses = $this->courseRepository->findAll();
        $response = [];
        foreach ($courses as $course) {
            $response[] = new CourseDTO($course);
        }
        return new JsonResponse($response, Response::HTTP_OK);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/courses/{code}",
     *     summary="Конкретный курс",
     *     description="Конкретный курс",
     * )
     * @OA\Response(
     *     response="200",
     *     description="Возвращает данные конкретного курса",
     *     @OA\JsonContent(
     *          @OA\Property(property="code", type="string", example="code-course-name"),
     *          @OA\Property(property="type", type="string", example="rent"),
     *          @OA\Property(property="price", type="float", example="99.90"),
     *     )
     * )
     * @OA\Response(
     *     response="404",
     *     description="Курс не найден",
     *     @OA\JsonContent(
     *          @OA\Property(property="code", type="string", example="404"),
     *          @OA\Property(property="message", type="string", example="Курс code-course-name не найден"),
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Ошибка",
     *     @OA\JsonContent(
     *          @OA\Property(property="code", type="string"),
     *          @OA\Property(property="message", type="string", example="Error"),
     *     )
     * )
     * @OA\Tag(name="Course")
     * @Route("/{code}", name="app_course_show", methods={"GET"})
     */
    public function getCourse(string $code): JsonResponse
    {
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if (is_null($course)) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Курс с кодом ' . $code . ' не найден.',
            ], Response::HTTP_NOT_FOUND);
        }
        $response = new CourseDTO($course);
        return new JsonResponse($response, Response::HTTP_OK);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/courses/{code}/pay",
     *     summary="Оплата курса",
     *     description="Оплата курса",
     * )
     * @OA\Response(
     *     response=200,
     *     description="Информация по купленному курсу",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="true"),
     *       @OA\Property(property="course_type", type="string", example="rent"),
     *       @OA\Property(property="expires_at", type="string", example="2019-05-20T13:46:07"),
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Пользователь не авторизован",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="401"),
     *       @OA\Property(property="message", type="string", example="Invalid credentials.")
     *     )
     * )
     * @OA\Response(
     *     response=406,
     *     description="Недостаточно средств",
     *     @OA\JsonContent(
     *       @OA\Property(property="code", type="string", example="406"),
     *       @OA\Property(property="message", type="string", example="На вашем счету недостаточно средств.")
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
     * @OA\Tag(name="Course")
     * @Route("/{code}/pay", name="app_course_pay", methods={"POST"})
     */
    public function buyCourse(string $code, PaymentService $paymentService): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Пользователь не авторизован.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if (is_null($course)) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Курс с кодом ' . $code . ' не найден.',
            ], Response::HTTP_NOT_FOUND);
        }
        if ($user->getBalance() < $course->getPrice()) {
            return new JsonResponse([
                'code' => 406,
                'message' => 'На вашем счету недостаточно средств.',
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
        try {
            $transaction = $paymentService->payment($user, $course);
            $expire = $transaction->getExpire();
            return new JsonResponse([
                'success' => true,
                'course_type' => $course->getType(),
                'expires_at' => $expire ?: null
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/courses",
     *     summary="Создание курса",
     *     description="Создание курса",
     * )
     * @OA\RequestBody(
     *   required=true,
     *   description="Параметры курса",
     *   @OA\JsonContent(
     *     @OA\Property(property="type", type="string", example="rent|free|buy"),
     *     @OA\Property(property="title", type="string", example="Название курса"),
     *     @OA\Property(property="code", type="string", example="course-code"),
     *     @OA\Property(property="price", type="number", example="1000"),
     *   )
     * )
     * @OA\Response(
     *     response=201,
     *     description="Курс добавлен",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="true"),
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Пользователь не авторизован",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="401"),
     *       @OA\Property(property="message", type="string", example="Invalid credentials.")
     *     )
     * )
     * @OA\Response(
     *     response=403,
     *     description="У вас не хватает прав",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="403"),
     *       @OA\Property(property="message", type="string", example="У вас недостаточно прав.")
     *     )
     * )
     * @OA\Response(
     *     response=409,
     *     description="Курс с таким кодом уже существует",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="409"),
     *       @OA\Property(property="message", type="string",
     *          example="Код курса должен быть уникален. Курс с таким кодом уже существует.")
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Ошибка",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="400"),
     *       @OA\Property(property="message", type="string", example="Error")
     *     )
     * )
     * @OA\Tag(name="Course")
     * @Security(name="Bearer")
     * @Route("", name="app_course_add", methods={"POST"})
     */
    public function addCourse(Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse([
                "success" => false,
                'code' => '401',
                'message' => 'JWT Token not found'
            ], Response::HTTP_UNAUTHORIZED);
        }
        if (!in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse([
                "success" => false,
                'code' => '403',
                'message' => 'У вас недостаточно прав.'
            ], Response::HTTP_FORBIDDEN);
        }
        $dto = $this->serializer->deserialize($request->getContent(), CourseDTO::class, 'json');
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $errors_json = [];
            foreach ($errors as $error) {
                $errors_json[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse([
                "success" => false,
                'code' => '400',
                'message' => $errors_json
            ], Response::HTTP_BAD_REQUEST);
        }
        if ($this->courseRepository->findOneBy(['code' => $dto->code])) {
            return new JsonResponse([
                "success" => false,
                'code' => '409',
                'message' => 'Код курса должен быть уникален. Курс с таким кодом уже существует.'
            ], Response::HTTP_CONFLICT);
        }
        $course = Course::fromDto($dto);
        $this->courseRepository->add($course, true);
        return new JsonResponse([
            "success" => true,
        ], Response::HTTP_CREATED);
    }
    /**
     * @OA\Post(
     *     path="/api/v1/courses/{code}",
     *     summary="Редактирование курса",
     *     description="Редактирование курса",
     * )
     * @OA\RequestBody(
     *   required=true,
     *   description="Параметры курса",
     *   @OA\JsonContent(
     *     @OA\Property(property="type", type="string", example="rent|free|buy"),
     *     @OA\Property(property="title", type="string", example="Название курса"),
     *     @OA\Property(property="code", type="string", example="course-code"),
     *     @OA\Property(property="price", type="number", example="1000"),
     *   )
     * )
     * @OA\Response(
     *     response=200,
     *     description="Курс добавлен",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="true"),
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Пользователь не авторизован",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="401"),
     *       @OA\Property(property="message", type="string", example="Invalid credentials.")
     *     )
     * )
     * @OA\Response(
     *     response=403,
     *     description="У вас не хватает прав",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="403"),
     *       @OA\Property(property="message", type="string", example="У вас недостаточно прав.")
     *     )
     * )
     * @OA\Response(
     *     response=404,
     *     description="Курс не найден",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="404"),
     *       @OA\Property(property="message", type="string", example="Курс с кодом code не найден.")
     *     )
     * )
     * @OA\Response(
     *     response=409,
     *     description="Курс с таким кодом уже существует",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="409"),
     *       @OA\Property(property="message", type="string",
     *          example="Курс с кодом code уже существует.")
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Ошибка",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="bool", example="false"),
     *       @OA\Property(property="code", type="string", example="400"),
     *       @OA\Property(property="message", type="string", example="Error")
     *     )
     * )
     * @OA\Tag(name="Course")
     * @Security(name="Bearer")
     * @Route("/{code}", name="app_course_edit", methods={"POST"})
     */
    public function editCourse(string $code, Request $request): JsonResponse
    {
        if (!in_array('ROLE_SUPER_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse([
                "success" => false,
                'code' => '403',
                'message' => 'У вас недостаточно прав.'
            ], Response::HTTP_FORBIDDEN);
        }
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if (!$course) {
            return new JsonResponse([
                "success" => false,
                'code' => '404',
                'message' => "Курс с кодом $code не найден."
            ], Response::HTTP_NOT_FOUND);
        }
        $dto = $this->serializer->deserialize($request->getContent(), CourseDTO::class, 'json');
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $errors_json = [];
            foreach ($errors as $error) {
                $errors_json[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse([
                "success" => false,
                'code' => '400',
                'message' => $errors_json
            ], Response::HTTP_BAD_REQUEST);
        }
        if ($code != $dto->code && $this->courseRepository->findOneBy(['code' => $dto->code])) {
            return new JsonResponse([
                "success" => false,
                'code' => '409',
                'message' => "Курс с кодом $dto->code уже существует."
            ], Response::HTTP_CONFLICT);
        }
        $this->courseRepository->add($course->updateFromDto($dto), true);
        return new JsonResponse([
            "success" => true,
        ], Response::HTTP_OK);
    }
}
