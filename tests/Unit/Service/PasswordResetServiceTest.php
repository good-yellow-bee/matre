<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\PasswordResetRequest;
use App\Entity\User;
use App\Repository\PasswordResetRequestRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetServiceTest extends TestCase
{
    public function testCreateResetRequestReturnsTrueForNonexistentUser(): void
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn(null);

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('sendPasswordResetEmail');

        $service = $this->createService(userRepository: $userRepository, emailService: $emailService);

        $this->assertTrue($service->createResetRequest('unknown@example.com'));
    }

    public function testCreateResetRequestReturnsTrueForDisabledUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isEnabled')->willReturn(false);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('sendPasswordResetEmail');

        $service = $this->createService(userRepository: $userRepository, emailService: $emailService);

        $this->assertTrue($service->createResetRequest('disabled@example.com'));
    }

    public function testCreateResetRequestReturnsTrueWhenMaxActiveRequestsReached(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isEnabled')->willReturn(true);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $resetRequestRepository = $this->createStub(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('countActiveForUser')->willReturn(3);

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->never())->method('sendPasswordResetEmail');

        $service = $this->createService(
            resetRequestRepository: $resetRequestRepository,
            userRepository: $userRepository,
            emailService: $emailService,
        );

        $this->assertTrue($service->createResetRequest('user@example.com'));
    }

    public function testCreateResetRequestCreatesRequestAndSendsEmail(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isEnabled')->willReturn(true);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('getUsername')->willReturn('testuser');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $resetRequestRepository = $this->createStub(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('countActiveForUser')->willReturn(0);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/reset/token123');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(PasswordResetRequest::class));
        $entityManager->expects($this->once())->method('flush');

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())->method('sendPasswordResetEmail')
            ->with('user@example.com', 'testuser', $this->isString(), 'https://example.com/reset/token123');

        $service = $this->createService(
            entityManager: $entityManager,
            resetRequestRepository: $resetRequestRepository,
            userRepository: $userRepository,
            emailService: $emailService,
            urlGenerator: $urlGenerator,
        );

        $this->assertTrue($service->createResetRequest('user@example.com', '127.0.0.1'));
    }

    public function testCreateResetRequestReturnsTrueWhenEmailSendingFails(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isEnabled')->willReturn(true);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('getUsername')->willReturn('testuser');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($user);

        $resetRequestRepository = $this->createStub(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('countActiveForUser')->willReturn(0);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://example.com/reset/token');

        $emailService = $this->createStub(EmailService::class);
        $emailService->method('sendPasswordResetEmail')
            ->willThrowException(new \RuntimeException('Template rendering failed'));

        $service = $this->createService(
            resetRequestRepository: $resetRequestRepository,
            userRepository: $userRepository,
            emailService: $emailService,
            urlGenerator: $urlGenerator,
        );

        $this->assertTrue($service->createResetRequest('user@example.com'));
    }

    public function testResetPasswordReturnsTrueWhenConfirmationEmailThrowsNonTransportException(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('getUsername')->willReturn('testuser');

        $resetRequest = $this->createMock(PasswordResetRequest::class);
        $resetRequest->method('getUser')->willReturn($user);
        $resetRequest->expects($this->once())->method('setIsUsed')->with(true);

        $resetRequestRepository = $this->createMock(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('findValidByToken')->willReturn($resetRequest);
        $resetRequestRepository->expects($this->once())->method('deleteForUser')->with($user);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())->method('sendPasswordChangedEmail')
            ->willThrowException(new \RuntimeException('Twig failure'));

        $service = $this->createService(
            entityManager: $entityManager,
            resetRequestRepository: $resetRequestRepository,
            emailService: $emailService,
            passwordHasher: $passwordHasher,
        );

        $this->assertTrue($service->resetPassword('valid-token', 'newpass123'));
    }

    public function testValidateTokenDelegatesToRepository(): void
    {
        $resetRequest = $this->createStub(PasswordResetRequest::class);

        $resetRequestRepository = $this->createStub(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('findValidByToken')
            ->willReturn($resetRequest);

        $service = $this->createService(resetRequestRepository: $resetRequestRepository);

        $this->assertSame($resetRequest, $service->validateToken('abc123'));
    }

    public function testResetPasswordReturnsFalseForInvalidToken(): void
    {
        $resetRequestRepository = $this->createStub(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('findValidByToken')->willReturn(null);

        $service = $this->createService(resetRequestRepository: $resetRequestRepository);

        $this->assertFalse($service->resetPassword('invalid-token', 'newpass'));
    }

    public function testResetPasswordHashesPasswordMarksUsedAndDeletesOtherRequests(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('getUsername')->willReturn('testuser');

        $resetRequest = $this->createMock(PasswordResetRequest::class);
        $resetRequest->method('getUser')->willReturn($user);
        $resetRequest->expects($this->once())->method('setIsUsed')->with(true);

        $resetRequestRepository = $this->createMock(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('findValidByToken')->willReturn($resetRequest);
        $resetRequestRepository->expects($this->once())->method('deleteForUser')->with($user);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed_password');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $emailService = $this->createMock(EmailService::class);
        $emailService->expects($this->once())->method('sendPasswordChangedEmail')
            ->with('user@example.com', 'testuser');

        $service = $this->createService(
            entityManager: $entityManager,
            resetRequestRepository: $resetRequestRepository,
            emailService: $emailService,
            passwordHasher: $passwordHasher,
        );

        $this->assertTrue($service->resetPassword('valid-token', 'newpass123'));
    }

    public function testCleanupExpiredRequestsDelegatesToRepository(): void
    {
        $resetRequestRepository = $this->createStub(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('deleteExpired')->willReturn(5);

        $service = $this->createService(resetRequestRepository: $resetRequestRepository);

        $this->assertSame(5, $service->cleanupExpiredRequests());
    }

    public function testCanRequestResetReturnsFalseForDisabledUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isEnabled')->willReturn(false);

        $this->assertFalse($this->createService()->canRequestReset($user));
    }

    public function testCanRequestResetReturnsFalseWhenAtMaxRequests(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isEnabled')->willReturn(true);

        $resetRequestRepository = $this->createStub(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('countActiveForUser')->willReturn(3);

        $service = $this->createService(resetRequestRepository: $resetRequestRepository);

        $this->assertFalse($service->canRequestReset($user));
    }

    public function testCanRequestResetReturnsTrueForValidUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('isEnabled')->willReturn(true);

        $resetRequestRepository = $this->createStub(PasswordResetRequestRepository::class);
        $resetRequestRepository->method('countActiveForUser')->willReturn(2);

        $service = $this->createService(resetRequestRepository: $resetRequestRepository);

        $this->assertTrue($service->canRequestReset($user));
    }

    public function testGetTokenLifetimeReturns3600(): void
    {
        $this->assertSame(3600, $this->createService()->getTokenLifetime());
    }

    private function createService(
        ?EntityManagerInterface $entityManager = null,
        ?PasswordResetRequestRepository $resetRequestRepository = null,
        ?UserRepository $userRepository = null,
        ?EmailService $emailService = null,
        ?UserPasswordHasherInterface $passwordHasher = null,
        ?UrlGeneratorInterface $urlGenerator = null,
        ?LoggerInterface $logger = null,
    ): PasswordResetService {
        return new PasswordResetService(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $resetRequestRepository ?? $this->createStub(PasswordResetRequestRepository::class),
            $userRepository ?? $this->createStub(UserRepository::class),
            $emailService ?? $this->createStub(EmailService::class),
            $passwordHasher ?? $this->createStub(UserPasswordHasherInterface::class),
            $urlGenerator ?? $this->createStub(UrlGeneratorInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
