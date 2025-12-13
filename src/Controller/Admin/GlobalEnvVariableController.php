<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin controller for Global Environment Variables.
 * CRUD operations are handled by Vue component via API.
 */
#[Route('/admin/env-variables')]
#[IsGranted('ROLE_ADMIN')]
class GlobalEnvVariableController extends AbstractController
{
    #[Route('', name: 'admin_env_variable_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/global_env_variable/index.html.twig');
    }
}
