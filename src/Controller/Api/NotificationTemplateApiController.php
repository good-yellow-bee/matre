<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\NotificationTemplate;
use App\Entity\User;
use App\Repository\NotificationTemplateRepository;
use App\Service\NotificationTemplateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notification-templates')]
#[IsGranted('ROLE_ADMIN')]
class NotificationTemplateApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationTemplateRepository $repository,
        private readonly NotificationTemplateService $templateService,
        private readonly MailerInterface $mailer,
        private readonly string $mailFrom = 'noreply@matre.local',
    ) {
    }

    #[Route('/{id}', name: 'api_notification_template_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(NotificationTemplate $template): JsonResponse
    {
        return $this->json([
            'id' => $template->getId(),
            'channel' => $template->getChannel(),
            'name' => $template->getName(),
            'nameLabel' => $template->getNameLabel(),
            'subject' => $template->getSubject(),
            'body' => $template->getBody(),
            'isActive' => $template->isActive(),
            'isDefault' => $template->isDefault(),
            'createdAt' => $template->getCreatedAt()->format('c'),
            'updatedAt' => $template->getUpdatedAt()?->format('c'),
        ]);
    }

    #[Route('/{id}', name: 'api_notification_template_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(Request $request, NotificationTemplate $template): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
        }

        if (isset($data['subject'])) {
            $template->setSubject($data['subject'] ?: null);
        }

        if (isset($data['body'])) {
            if (empty(trim($data['body']))) {
                return $this->json(['error' => 'Template body cannot be empty.'], 422);
            }
            $template->setBody($data['body']);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Template updated successfully.',
        ]);
    }

    #[Route('/{id}/preview', name: 'api_notification_template_preview', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function preview(Request $request, NotificationTemplate $template): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
        }

        $subject = $data['subject'] ?? $template->getSubject() ?? '';
        $body = $data['body'] ?? $template->getBody();

        $rendered = $this->templateService->renderPreview($subject, $body, $template->getChannel());

        // For email templates, return both subject and body
        // For Slack templates, format as Slack markdown preview
        if (NotificationTemplate::CHANNEL_EMAIL === $template->getChannel()) {
            return $this->json([
                'subject' => $rendered['subject'],
                'html' => $rendered['body'],
            ]);
        }

        // For Slack, return the text rendered
        return $this->json([
            'subject' => null,
            'html' => '<pre style="white-space: pre-wrap; font-family: inherit;">' . htmlspecialchars($rendered['body']) . '</pre>',
        ]);
    }

    #[Route('/{id}/test-send', name: 'api_notification_template_test_send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function testSend(Request $request, NotificationTemplate $template): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json(['error' => 'Invalid JSON: ' . json_last_error_msg()], 400);
        }

        $subject = $data['subject'] ?? $template->getSubject() ?? '';
        $body = $data['body'] ?? $template->getBody();

        $rendered = $this->templateService->renderPreview($subject, $body, $template->getChannel());

        /** @var User $user */
        $user = $this->getUser();

        if (NotificationTemplate::CHANNEL_EMAIL === $template->getChannel()) {
            if (!$user->getEmail()) {
                return $this->json(['error' => 'No email address configured for your account.'], 400);
            }

            try {
                $this->sendTestEmail($rendered['subject'] ?? '[Test] Notification Template', $rendered['body'], $user->getEmail());
            } catch (TransportExceptionInterface $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Email delivery failed: ' . $e->getMessage(),
                ], 500);
            }

            return $this->json([
                'success' => true,
                'message' => 'Test email sent to ' . $user->getEmail(),
            ]);
        }

        // Slack test - would need webhook configured
        return $this->json([
            'success' => false,
            'message' => 'Slack test sending not implemented yet. Please test by triggering an actual test run.',
        ], 501);
    }

    #[Route('/variables', name: 'api_notification_template_variables', methods: ['GET'])]
    public function variables(): JsonResponse
    {
        return $this->json($this->templateService->getAvailableVariables());
    }

    #[Route('/{id}/reset', name: 'api_notification_template_reset', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reset(NotificationTemplate $template): JsonResponse
    {
        $defaults = $this->templateService->getDefaultTemplateContent(
            $template->getChannel(),
            $template->getName(),
        );

        if (!$defaults['body']) {
            return $this->json(['error' => 'No default template found.'], 404);
        }

        $template->setSubject($defaults['subject']);
        $template->setBody($defaults['body']);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Template reset to default.',
            'subject' => $template->getSubject(),
            'body' => $template->getBody(),
        ]);
    }

    private function sendTestEmail(string $subject, string $body, string $recipient): void
    {
        $email = (new Email())
            ->from($this->mailFrom)
            ->to($recipient)
            ->subject($subject)
            ->html($body);

        $this->mailer->send($email);
    }
}
