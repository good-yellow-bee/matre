<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User profile pages (SPA shell).
 */
#[Route('/admin/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/notifications', name: 'admin_profile_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
