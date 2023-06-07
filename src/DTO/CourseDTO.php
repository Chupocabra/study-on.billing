<?php

namespace App\DTO;

use App\Entity\Course;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

class CourseDTO
{
    /**
     * @Serializer\Type("string")
     * @Assert\NotBlank(message="Укажите код курса")
     */
    public string $code;
    /**
     * @Serializer\Type("string")
     * @Assert\NotBlank(message="Укажите тип курса")
     */
    public string $type;
    /**
     * @Serializer\Type("float")
     * @Assert\NotBlank(message="Укажите стоимость курса")
     */
    public float $price;

    /**
     * @Serializer\Type("string")
     * @Assert\NotBlank(message="Укажите название курса")
     */
    public string $title;
    public function __construct(Course $course = null)
    {
        if (!is_null($course)) {
            $this->code = $course->getCode();
            $this->type = $course->getType();
            $this->price = $course->getPrice();
            $this->title = $course->getTitle();
        }
    }
}
