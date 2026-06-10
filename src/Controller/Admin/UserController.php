<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin user pages (SPA shell).
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/{id}', name: 'admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
