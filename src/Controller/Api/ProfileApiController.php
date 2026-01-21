<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TestEnvironment;
use App\Entity\User;
use App\Repository\TestEnvironmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/profile')]
#[IsGranted('ROLE_USER')]
class ProfileApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TestEnvironmentRepository $environmentRepository,
    ) {
    }

    #[Route('/notifications', name: 'api_profile_notifications_get', methods: ['GET'])]
    public function getNotifications(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'notificationsEnabled' => $user->isNotificationsEnabled(),
            'notificationTrigger' => $user->getNotificationTrigger(),
            'notifyByEmail' => $user->isNotifyByEmail(),
            'notificationEnvironments' => array_map(
                fn (TestEnvironment $env) => $env->getId(),
                $user->getNotificationEnvironments()->toArray(),
            ),
        ]);
    }

    #[Route('/notifications', name: 'api_profile_notifications_update', methods: ['PUT'])]
    public function updateNotifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        $user->setNotificationsEnabled($data['notificationsEnabled'] ?? false);

        $trigger = $data['notificationTrigger'] ?? 'failures';
        if (!in_array($trigger, ['all', 'failures'], true)) {
            $trigger = 'failures';
        }
        $user->setNotificationTrigger($trigger);

        $user->setNotifyByEmail($data['notifyByEmail'] ?? false);

        // Sync notification environments
        foreach ($user->getNotificationEnvironments() as $env) {
            $user->removeNotificationEnvironment($env);
        }

        $environmentIds = $data['notificationEnvironments'] ?? [];
        foreach ($environmentIds as $id) {
            $env = $this->environmentRepository->find($id);
            if ($env) {
                $user->addNotificationEnvironment($env);
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Notification settings updated successfully',
        ]);
    }

    #[Route('/environments', name: 'api_profile_environments', methods: ['GET'])]
    public function getEnvironments(): JsonResponse
    {
        $environments = $this->environmentRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->json(array_map(fn (TestEnvironment $e) => [
            'id' => $e->getId(),
            'name' => $e->getName(),
        ], $environments));
    }
}
