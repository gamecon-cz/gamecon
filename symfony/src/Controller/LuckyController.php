<?php

declare(strict_types=1);

namespace Gamecon\Symfony\Controller;

use Gamecon\Symfony\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class LuckyController extends AbstractController
{
    public function number(UserRepository $userRepository)
    {
        $number = random_int(0, 100);

        $user = $userRepository->findOneBy(['id' => 1]);

        var_dump($user);

        return new Response(
            '<html lang="en"><body>Lucky number: ' . $number . '</body></html>'
        );
    }
}
