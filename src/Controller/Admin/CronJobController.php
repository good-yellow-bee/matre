<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the SPA shell for cron job pages (data via CronJobApiController).
 */
#[Route('/admin/cron-jobs')]
#[IsGranted('ROLE_ADMIN')]
class CronJobController extends AbstractController
{
    #[Route('', name: 'admin_cron_job_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/new', name: 'admin_cron_job_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/{id}', name: 'admin_cron_job_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(): Response
    {
        return $this->render('spa/index.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_cron_job_edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
