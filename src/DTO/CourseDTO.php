<?php

namespace App\DTO;

use App\Entity\Course;
use JMS\Serializer\Annotation as Serializer;

class CourseDTO
{
    /**
     * @Serializer\Type("string")
     */
    public string $code;
    /**
     * @Serializer\Type("string")
     */
    public string $type;
    /**
     * @Serializer\Type("float")
     */
    public float $price;

    public function __construct(Course $course = null)
    {
        if (!is_null($course)) {
            $this->code = $course->getCode();
            $this->type = $course->getType();
            $this->price = $course->getPrice();
        }
    }
}
