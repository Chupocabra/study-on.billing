<?php

namespace App\Controller;

use App\DTO\CourseDTO;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/v1/courses")
 */
class CoursesController extends AbstractController
{
    private CourseRepository $courseRepository;

    public function __construct(CourseRepository $courseRepository)
    {
        $this->courseRepository = $courseRepository;
    }

    /**
     * @OA\Get(
     *     path="",
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
     *     path="/{code}",
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
     *     path="/{code}/pay",
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
     * )* @OA\Response(
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
}
