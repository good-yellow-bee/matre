<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the SPA shell for the settings page (data via SettingsApiController).
 */
#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class SettingsController extends AbstractController
{
    #[Route('', name: 'admin_settings_edit', methods: ['GET'])]
    public function edit(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
