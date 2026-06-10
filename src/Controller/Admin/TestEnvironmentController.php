<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/test-environments')]
#[IsGranted('ROLE_ADMIN')]
class TestEnvironmentController extends AbstractController
{
    #[Route('', name: 'admin_test_environment_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/new', name: 'admin_test_environment_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/{id}', name: 'admin_test_environment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_test_environment_edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
