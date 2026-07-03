<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the SPA shell for all client-routed paths (lowest priority, GET only).
 *
 * The named /login and /2fa-setup routes exist because security code generates URLs for them (app_login, 2fa_setup).
 */
class SpaController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET'], priority: -90)]
    #[Route('/2fa-setup', name: '2fa_setup', methods: ['GET'], priority: -90)]
    #[Route(
        '/{path}',
        name: 'spa_shell',
        requirements: ['path' => '(?!api(/|$)|build/|_profiler|_wdt|2fa_check$|logout$).*'],
        defaults: ['path' => ''],
        methods: ['GET'],
        priority: -100,
    )]
    public function shell(): Response
    {
        return $this->render('spa/index.html.twig');
    }
}
