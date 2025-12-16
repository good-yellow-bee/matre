<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\NotificationPreferencesType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User Profile Controller.
 *
 * Handles user's own profile settings including notification preferences
 */
#[Route('/admin/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Edit notification preferences.
     */
    #[Route('/notifications', name: 'admin_profile_notifications', methods: ['GET', 'POST'])]
    public function notifications(Request $request): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(NotificationPreferencesType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Notification preferences updated.');

            return $this->redirectToRoute('admin_profile_notifications');
        }

        return $this->render('admin/profile/notifications.html.twig', [
            'form' => $form,
        ]);
    }
}
