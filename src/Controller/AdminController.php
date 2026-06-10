<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_USER')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
