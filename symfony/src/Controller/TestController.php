<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class TestController extends AbstractController
{
    public function index(): Response
    {
        return new Response('
            <h1>Symfony Test Page</h1>
            <p>If you can see this, Symfony routing is working!</p>
            <p>Environment: ' . $this->getParameter('kernel.environment') . '</p>
            <p><a href="/admin/">Back to admin</a></p>
        ');
    }
}