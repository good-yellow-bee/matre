<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the SPA shell for notification template pages (data via NotificationTemplateApiController).
 */
#[Route('/admin/notification-templates')]
#[IsGranted('ROLE_ADMIN')]
class NotificationTemplateController extends AbstractController
{
    #[Route('', name: 'admin_notification_template_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_notification_template_edit', methods: ['GET'])]
    public function edit(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
