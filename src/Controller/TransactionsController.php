<?php

namespace App\Controller;

use App\DTO\TransactionDTO;
use App\Entity\User;
use App\Repository\TransactionRepository;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/v1/transactions")
 */
class TransactionsController extends AbstractController
{
    /**
     * @OA\Get(
     *     path="/api/v1/transactions",
     *     summary="Получить все транзакции пользователя с фильтрами",
     *     description="Получить все транзакции пользователя с фильтрами",
     * )
     * @OA\Parameter(
     *     name="type",
     *     description="Тип транзакции (payment|deposit)",
     *     in="query",
     *     example="payment",
     * )
     * @OA\Parameter(
     *     name="course_code",
     *     description="Символьный код курса",
     *     in="query",
     *     example="data_analyst",
     * )
     * @OA\Parameter(
     *     name="skip_expired",
     *     description="Отбросить оплаты аренд в прошлом",
     *     in="query",
     *     example="true",
     * )
     * @OA\Response(
     *     response="200",
     *     description="Возвращает данные транзакций пользователя",
     *     @OA\JsonContent(
     *          type="array",
     *          @OA\Items(
     *            @OA\Property(property="id", type="string"),
     *            @OA\Property(property="created_at", type="string"),
     *            @OA\Property(property="type", type="string"),
     *            @OA\Property(property="course_code", type="string"),
     *            @OA\Property(property="value", type="string"),
     *            @OA\Property(property="expires", type="string"),
     *          )
     *     )
     * )
     * @OA\Response(
     *     response="401",
     *     description="Пользователь не авторизован",
     *     @OA\JsonContent(
     *          @OA\Property(property="code", type="string", example="401"),
     *          @OA\Property(property="message", type="string", example="JWT Token not found"),
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
     * @OA\Tag(name="Transactions")
     * @Security(name="Bearer")
     * @Route("", name="transactions_history", methods={"GET"})
     */
    public function getHistory(Request $request, TransactionRepository $transactionRepository): JsonResponse
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Пользователь не авторизован.'
            ], Response::HTTP_UNAUTHORIZED);
        }
        $filters = [];
        // тип транзакции payment|deposit
        if ($request->query->get('type') === 'payment') {
            $filters['type'] = 1;
        } elseif ($request->query->get('type') === 'deposit') {
            $filters['type'] = 2;
        } else {
            $filters['type'] = null;
        }
        // символьный код курса
        $filters['course_code'] = $request->query->get('course_code');
        // флаг, позволяющий отбросить записи оплаты аренд, которые уже истекли
        $filters['skip_expired'] = $request->query->get('skip_expired');
        $transactions = $transactionRepository->findFilteredTransactions($user, $filters);
        $response = [];
        foreach ($transactions as $transaction) {
            $response[] = new TransactionDTO($transaction);
        }
        return new JsonResponse($response, Response::HTTP_OK);
    }
}
