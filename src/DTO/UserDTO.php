<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserDTO
{
    /**
     * @Serializer\Type("string")
     * @ASsert\Email(message="Неверный почтовый адрес")
     * @Assert\NotBlank(message="Укажите почтовый адрес")
     */
    public string $username;
    /**
     * @Serializer\Type("string")
     * @Assert\Length(min=6, max=16,
     *     minMessage="Пароль должен содержать не менее {{ limit }} символов",
     *     maxMessage="Пароль должен содержать не более {{ limit }} символов")
     * @Assert\NotBlank(message="Заполните поле с паролем")
     */
    public string $password;
}
