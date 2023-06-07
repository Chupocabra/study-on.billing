<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture implements OrderedFixtureInterface
{
    public function load(ObjectManager $manager)
    {
        // type 1 -- rent
        // 2 -- free
        // 3 -- buy
        $courses = [
            [
                'title' => 'Фронтенд-разработчик',
                'code' => 'frontend-dev',
                'type' => 2,
                'price' => 0,
            ],
            [
                'title' => 'Python-разработчик',
                'code' => 'python-dev',
                'type' => 1,
                'price' => 1000,
            ],
            [
                'title' => 'Аналитик данных',
                'code' => 'data-analyst',
                'type' => 1,
                'price' => 800,
            ],
            [
                'title' => 'Java-разработчик',
                'code' => 'java-dev',
                'type' => 3,
                'price' => 2800,
            ],
            [
                'title' => 'PHP-разработчик',
                'code' => 'php-dev',
                'type' => 3,
                'price' => 3200,
            ],
        ];
        foreach ($courses as $c) {
            $course = new Course();
            $course
                ->setCode($c['code'])
                ->setType($c['type'])
                ->setPrice($c['price'])
                ->setTitle($c['title']);
            $manager->persist($course);
        }
        $manager->flush();
    }
    public function getOrder(): int
    {
        return 2;
    }
}
