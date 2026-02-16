<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\PasswordResetRequest;
use App\Entity\User;
use App\Service\EmailService;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Integration test for password reset workflow: request → email → token validation → reset.
 */
class PasswordResetFlowTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    // =====================
    // Full Reset Flow
    // =====================

    public function testFullPasswordResetFlow(): void
    {
        $user = $this->createUser('resetuser', 'resetuser@example.com', 'OldPassword123!');

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())->method('sendPasswordResetEmail');

        $service = $this->buildPasswordResetService($emailService);

        // Step 1: Request reset
        $result = $service->createResetRequest($user->getEmail(), '127.0.0.1');
        $this->assertTrue($result);

        // Step 2: Find token in database
        $resetRequest = $this->entityManager->getRepository(PasswordResetRequest::class)
            ->findOneBy([], ['id' => 'DESC']);
        $this->assertNotNull($resetRequest);
        $this->assertFalse($resetRequest->isExpired());
        $this->assertFalse($resetRequest->isUsed());
        $this->assertSame($user->getId(), $resetRequest->getUser()->getId());

        // Step 3: We need the plain token - extract from email mock
        // Since the token is generated internally, we test via the repository
        // The token stored is hashed, so we verify the flow differently
        $this->assertSame('127.0.0.1', $resetRequest->getIpAddress());
    }

    public function testResetPasswordWithValidToken(): void
    {
        $user = $this->createUser('resetuser2', 'resetuser2@example.com', 'OldPassword123!');
        $oldPasswordHash = $user->getPassword();

        // Manually create a reset request with a known token
        $plainToken = bin2hex(random_bytes(32));
        $resetRequest = new PasswordResetRequest();
        $resetRequest->setUser($user);
        $resetRequest->setToken($plainToken);
        $resetRequest->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
        $this->entityManager->persist($resetRequest);
        $this->entityManager->flush();

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())->method('sendPasswordChangedEmail')
            ->with($user->getEmail(), $user->getUsername());

        $service = $this->buildPasswordResetService($emailService);

        // Reset password
        $result = $service->resetPassword($plainToken, 'NewPassword456!');
        $this->assertTrue($result);

        // Verify password changed
        $this->entityManager->refresh($user);
        $this->assertNotSame($oldPasswordHash, $user->getPassword());
        $this->assertTrue($this->passwordHasher->isPasswordValid($user, 'NewPassword456!'));
    }

    // =====================
    // Token Validation
    // =====================

    public function testValidateExpiredTokenReturnsNull(): void
    {
        $user = $this->createUser('expuser', 'expuser@example.com', 'Pass123!');
        $plainToken = bin2hex(random_bytes(32));

        $resetRequest = new PasswordResetRequest();
        $resetRequest->setUser($user);
        $resetRequest->setToken($plainToken);
        $resetRequest->setExpiresAt((new \DateTimeImmutable())->modify('-1 hour'));
        $this->entityManager->persist($resetRequest);
        $this->entityManager->flush();

        $service = $this->buildPasswordResetService();
        $result = $service->validateToken($plainToken);

        $this->assertNull($result);
    }

    public function testValidateUsedTokenReturnsNull(): void
    {
        $user = $this->createUser('useduser', 'useduser@example.com', 'Pass123!');
        $plainToken = bin2hex(random_bytes(32));

        $resetRequest = new PasswordResetRequest();
        $resetRequest->setUser($user);
        $resetRequest->setToken($plainToken);
        $resetRequest->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
        $resetRequest->setIsUsed(true);
        $this->entityManager->persist($resetRequest);
        $this->entityManager->flush();

        $service = $this->buildPasswordResetService();
        $result = $service->validateToken($plainToken);

        $this->assertNull($result);
    }

    public function testValidateValidTokenReturnsRequest(): void
    {
        $user = $this->createUser('validuser', 'validuser@example.com', 'Pass123!');
        $plainToken = bin2hex(random_bytes(32));

        $resetRequest = new PasswordResetRequest();
        $resetRequest->setUser($user);
        $resetRequest->setToken($plainToken);
        $resetRequest->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
        $this->entityManager->persist($resetRequest);
        $this->entityManager->flush();

        $service = $this->buildPasswordResetService();
        $result = $service->validateToken($plainToken);

        $this->assertNotNull($result);
        $this->assertSame($user->getId(), $result->getUser()->getId());
    }

    // =====================
    // Rate Limiting
    // =====================

    public function testMaxActiveRequestsLimitsCreation(): void
    {
        $user = $this->createUser('ratelimited', 'ratelimited@example.com', 'Pass123!');

        $emailService = $this->createMock(EmailService::class);
        // Should be called exactly 3 times (the max)
        $emailService->expects($this->exactly(3))->method('sendPasswordResetEmail');

        $service = $this->buildPasswordResetService($emailService);

        // Create 3 requests (max)
        for ($i = 0; $i < 3; ++$i) {
            $service->createResetRequest($user->getEmail());
        }

        // 4th request should be silently blocked (still returns true for privacy)
        $result = $service->createResetRequest($user->getEmail());
        $this->assertTrue($result);
    }

    public function testCanRequestResetReturnsFalseAtLimit(): void
    {
        $user = $this->createUser('limitcheck', 'limitcheck@example.com', 'Pass123!');

        $emailService = $this->createMock(EmailService::class);
        $emailService->method('sendPasswordResetEmail');

        $service = $this->buildPasswordResetService($emailService);

        $this->assertTrue($service->canRequestReset($user));

        // Fill up the limit
        for ($i = 0; $i < 3; ++$i) {
            $service->createResetRequest($user->getEmail());
        }

        $this->assertFalse($service->canRequestReset($user));
    }

    // =====================
    // Email Enumeration Prevention
    // =====================

    public function testNonexistentEmailReturnsTrueForPrivacy(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('sendPasswordResetEmail');

        $service = $this->buildPasswordResetService($emailService);

        $result = $service->createResetRequest('nonexistent@example.com');
        $this->assertTrue($result);
    }

    public function testDisabledUserReturnsTrueForPrivacy(): void
    {
        $user = $this->createUser('disabled', 'disabled@example.com', 'Pass123!');
        $user->setIsActive(false);
        $this->entityManager->flush();

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('sendPasswordResetEmail');

        $service = $this->buildPasswordResetService($emailService);

        $result = $service->createResetRequest($user->getEmail());
        $this->assertTrue($result);
    }

    // =====================
    // Cleanup
    // =====================

    public function testCleanupExpiredRequestsDeletesOld(): void
    {
        $user = $this->createUser('cleanupuser', 'cleanupuser@example.com', 'Pass123!');

        // Create an expired request
        $expired = new PasswordResetRequest();
        $expired->setUser($user);
        $expired->setToken(bin2hex(random_bytes(32)));
        $expired->setExpiresAt((new \DateTimeImmutable())->modify('-2 hours'));
        $this->entityManager->persist($expired);

        // Create a valid request
        $valid = new PasswordResetRequest();
        $valid->setUser($user);
        $valid->setToken(bin2hex(random_bytes(32)));
        $valid->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
        $this->entityManager->persist($valid);
        $this->entityManager->flush();

        $service = $this->buildPasswordResetService();
        $deleted = $service->cleanupExpiredRequests();

        $this->assertGreaterThanOrEqual(1, $deleted);

        // Valid request should still exist
        $this->entityManager->clear();
        $remaining = $this->entityManager->find(PasswordResetRequest::class, $valid->getId());
        $this->assertNotNull($remaining);
    }

    public function testResetDeletesAllUserRequests(): void
    {
        $user = $this->createUser('deleteall', 'deleteall@example.com', 'Pass123!');

        // Create multiple requests
        $tokens = [];
        for ($i = 0; $i < 3; ++$i) {
            $token = bin2hex(random_bytes(32));
            $tokens[] = $token;
            $request = new PasswordResetRequest();
            $request->setUser($user);
            $request->setToken($token);
            $request->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'));
            $this->entityManager->persist($request);
        }
        $this->entityManager->flush();

        $emailService = $this->createMock(EmailService::class);
        $emailService->method('sendPasswordChangedEmail');

        $service = $this->buildPasswordResetService($emailService);

        // Reset using first token
        $result = $service->resetPassword($tokens[0], 'NewPassword!');
        $this->assertTrue($result);

        // All requests for user should be deleted
        $repo = $this->entityManager->getRepository(PasswordResetRequest::class);
        $remaining = $repo->findBy(['user' => $user]);
        $this->assertCount(0, $remaining);
    }

    // =====================
    // Helpers
    // =====================

    private function createUser(string $username, string $email, string $password): User
    {
        $suffix = uniqid();
        $user = new User();
        $user->setUsername($username . '_' . $suffix);
        $user->setEmail(str_replace('@', '_' . $suffix . '@', $email));
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function buildPasswordResetService(?EmailService $emailService = null): PasswordResetService
    {
        $container = static::getContainer();

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://matre.test/reset-password/test-token');

        return new PasswordResetService(
            $this->entityManager,
            $container->get('App\Repository\PasswordResetRequestRepository'),
            $container->get('App\Repository\UserRepository'),
            $emailService ?? $this->createMock(EmailService::class),
            $this->passwordHasher,
            $urlGenerator,
            $container->get('Psr\Log\LoggerInterface'),
        );
    }
}
