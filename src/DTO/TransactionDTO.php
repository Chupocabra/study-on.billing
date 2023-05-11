<?php

namespace App\DTO;

use App\Entity\Transaction;
use DateTimeImmutable;
use JMS\Serializer\Annotation as Serializer;

class TransactionDTO
{
    /**
     * @Serializer\Type("int")
     */
    public int $id;
    /**
     * @Serializer\Type("DateTimeImmutable")
     */
    public ?DateTimeImmutable $created_at;
    /**
     * @Serializer\Type("string")
     */
    public ?string $type;
    /**
     * @Serializer\Type("string")
     * @Serializer\SkipWhenEmpty()
     */
    public ?string $course_code;
    /**
     * @Serializer\Type("float")
     */
    public ?float $value;
    /**
     * @Serializer\Type("DateTimeImmutable")
     * @Serializer\SkipWhenEmpty()
     */
    public ?DateTimeImmutable $expires;

    public function __construct(Transaction $transaction)
    {
        $this->id = $transaction->getId();
        $this->created_at = $transaction->getDateTime();
        $this->type = $transaction->getType();
        if (!is_null($transaction->getCourse())) {
            $this->course_code = $transaction->getCourse()->getCode();
        }
        $this->value = $transaction->getValue();
        $this->expires = $transaction->getExpire();
    }
}
