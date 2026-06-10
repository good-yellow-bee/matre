<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/settings')]
#[IsGranted('ROLE_ADMIN')]
class SettingsApiController extends AbstractController
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_settings_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $settings = $this->settingsRepository->getOrCreate();

        return $this->json([
            'siteName' => $settings->getSiteName(),
            'adminPanelTitle' => $settings->getAdminPanelTitle(),
            'seoDescription' => $settings->getSeoDescription(),
            'seoKeywords' => $settings->getSeoKeywords(),
            'defaultLocale' => $settings->getDefaultLocale(),
            'headlessMode' => $settings->isHeadlessMode(),
            'autoReportForIndividualRuns' => $settings->isAutoReportForIndividualRuns(),
            'enforce2fa' => $settings->isEnforce2fa(),
            'maxRetryCount' => $settings->getMaxRetryCount(),
            'updatedAt' => $settings->getUpdatedAt()?->format('c'),
        ]);
    }

    #[Route('', name: 'api_settings_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $settings = $this->settingsRepository->getOrCreate();
        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['siteName'])) {
            $settings->setSiteName((string) $data['siteName']);
        }
        if (isset($data['adminPanelTitle'])) {
            $settings->setAdminPanelTitle((string) $data['adminPanelTitle']);
        }
        if (\array_key_exists('seoDescription', $data)) {
            $settings->setSeoDescription($data['seoDescription'] ?? null);
        }
        if (\array_key_exists('seoKeywords', $data)) {
            $settings->setSeoKeywords($data['seoKeywords'] ?? null);
        }
        if (isset($data['defaultLocale'])) {
            $settings->setDefaultLocale((string) $data['defaultLocale']);
        }
        if (isset($data['headlessMode'])) {
            $settings->setHeadlessMode((bool) $data['headlessMode']);
        }
        if (isset($data['autoReportForIndividualRuns'])) {
            $settings->setAutoReportForIndividualRuns((bool) $data['autoReportForIndividualRuns']);
        }
        if (isset($data['enforce2fa'])) {
            $settings->setEnforce2fa((bool) $data['enforce2fa']);
        }
        if (isset($data['maxRetryCount'])) {
            $settings->setMaxRetryCount((int) $data['maxRetryCount']);
        }

        $errors = $this->validator->validate($settings);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], 422);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }
}
