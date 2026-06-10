<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Security\CredentialEncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * TOTP 2FA setup endpoints for the SPA.
 */
#[Route('/api/2fa-setup')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TwoFactorSetupApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly CredentialEncryptionService $encryptionService,
    ) {
    }

    #[Route('', name: 'api_2fa_setup', methods: ['POST'])]
    public function setup(Request $request): JsonResponse
    {
        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTotpEnabled()) {
            return $this->json(['enabled' => true]);
        }

        if (!$user->getTotpSecret()) {
            $secret = $this->totpAuthenticator->generateSecret();
            // Encrypt explicitly: raw Base32 secrets false-positive CredentialEncryptionService::isEncrypted(), so the listener would store them unencrypted and brick the account on next load
            $user->setTotpSecret($this->encryptionService->encrypt($secret));
            $this->entityManager->flush();
            $user->setTotpSecret($secret);
        }

        $builder = new Builder(
            writer: new PngWriter(),
            data: $this->totpAuthenticator->getQRContent($user),
            encoding: new Encoding('UTF-8'),
            size: 200,
            margin: 10,
        );

        return $this->json([
            'enabled' => false,
            'qrCode' => $builder->build()->getDataUri(),
            'secret' => $user->getTotpSecret(),
        ]);
    }

    #[Route('/verify', name: 'api_2fa_setup_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $token)) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$user->getTotpSecret()) {
            return $this->json(['success' => false, 'error' => 'Two-factor setup has not been initiated'], 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $code = (string) ($data['code'] ?? '');

        if (!$this->totpAuthenticator->checkCode($user, $code)) {
            return $this->json(['success' => false, 'error' => 'Invalid verification code'], 400);
        }

        $user->setIsTotpEnabled(true);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
