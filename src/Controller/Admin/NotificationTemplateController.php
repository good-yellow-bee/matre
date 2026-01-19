<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NotificationTemplate;
use App\Repository\NotificationTemplateRepository;
use App\Service\NotificationTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notification-templates')]
#[IsGranted('ROLE_ADMIN')]
class NotificationTemplateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationTemplateRepository $repository,
        private readonly NotificationTemplateService $templateService,
    ) {
    }

    #[Route('', name: 'admin_notification_template_index', methods: ['GET'])]
    public function index(): Response
    {
        $templates = $this->repository->findAllGroupedByChannel();

        return $this->render('admin/notification_template/index.html.twig', [
            'templates' => $templates,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_notification_template_edit', methods: ['GET'])]
    public function edit(NotificationTemplate $template): Response
    {
        return $this->render('admin/notification_template/edit.html.twig', [
            'template' => $template,
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_notification_template_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, NotificationTemplate $template): Response
    {
        if (!$this->isCsrfTokenValid('toggle-active-' . $template->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_notification_template_index');
        }

        $template->setIsActive(!$template->isActive());
        $this->entityManager->flush();

        $status = $template->isActive() ? 'activated' : 'deactivated';
        $this->addFlash('success', "Template {$status} successfully.");

        return $this->redirectToRoute('admin_notification_template_index');
    }

    #[Route('/reset-defaults', name: 'admin_notification_template_reset_defaults', methods: ['POST'])]
    public function resetDefaults(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reset-defaults', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_notification_template_index');
        }

        $templates = $this->repository->findAll();

        foreach ($templates as $template) {
            $defaults = $this->templateService->getDefaultTemplateContent(
                $template->getChannel(),
                $template->getName(),
            );

            if ($defaults['body']) {
                $template->setSubject($defaults['subject']);
                $template->setBody($defaults['body']);
                $template->setIsActive(true);
            }
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'All templates have been reset to defaults.');

        return $this->redirectToRoute('admin_notification_template_index');
    }
}
