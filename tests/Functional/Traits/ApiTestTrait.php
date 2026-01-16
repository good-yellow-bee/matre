<?php

declare(strict_types=1);

namespace App\Tests\Functional\Traits;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Helper trait for API functional tests.
 */
trait ApiTestTrait
{
    protected ?EntityManagerInterface $entityManager = null;

    protected function getEntityManager(): EntityManagerInterface
    {
        if (null === $this->entityManager) {
            $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        }

        return $this->entityManager;
    }

    protected function createUser(
        ?string $username = null,
        ?string $email = null,
        string $password = 'TestPass123!',
        array $roles = ['ROLE_USER'],
        bool $active = true,
    ): User {
        $em = $this->getEntityManager();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $username ??= 'testuser_' . $suffix;
        $email ??= "test_{$suffix}@example.com";

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setIsActive($active);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    protected function createAdminUser(
        ?string $username = null,
        ?string $email = null,
        string $password = 'AdminPass123!',
    ): User {
        $suffix = bin2hex(random_bytes(4));
        $username ??= 'admin_' . $suffix;
        $email ??= "admin_{$suffix}@example.com";

        return $this->createUser($username, $email, $password, ['ROLE_ADMIN']);
    }

    protected function loginAsUser(KernelBrowser $client, ?User $user = null): User
    {
        $user ??= $this->createUser();
        $client->loginUser($user);

        return $user;
    }

    protected function loginAsAdmin(KernelBrowser $client, ?User $user = null): User
    {
        $user ??= $this->createAdminUser();
        $client->loginUser($user);

        return $user;
    }

    protected function getCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        // Use stateless CSRF tokens (configured in config/packages/test/csrf.yaml)
        $csrfManager = static::getContainer()->get(CsrfTokenManagerInterface::class);

        return $csrfManager->getToken($tokenId)->getValue();
    }

    protected function jsonRequest(
        KernelBrowser $client,
        string $method,
        string $url,
        array $data = [],
        array $headers = [],
        ?string $csrfTokenId = null,
    ): Response {
        $requestHeaders = array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $headers);

        if (null !== $csrfTokenId) {
            $requestHeaders['HTTP_X-CSRF-Token'] = $this->getCsrfToken($client, $csrfTokenId);
        }

        $client->request(
            $method,
            $url,
            [],
            [],
            $requestHeaders,
            empty($data) ? null : json_encode($data),
        );

        return $client->getResponse();
    }

    protected function assertJsonResponse(Response $response, int $statusCode = 200): array
    {
        $this->assertEquals($statusCode, $response->getStatusCode(), sprintf(
            'Expected status %d, got %d. Response: %s',
            $statusCode,
            $response->getStatusCode(),
            $response->getContent(),
        ));

        $this->assertJson($response->getContent());

        return json_decode($response->getContent(), true);
    }

    protected function assertJsonError(Response $response, int $statusCode, ?string $messageContains = null): void
    {
        $data = $this->assertJsonResponse($response, $statusCode);

        $this->assertTrue(
            isset($data['error']) || isset($data['errors']),
            'Response should contain "error" or "errors" key',
        );

        if (null !== $messageContains) {
            $errorMessage = $data['error'] ?? json_encode($data['errors']);
            $this->assertStringContainsString($messageContains, $errorMessage);
        }
    }

    protected function cleanupUsers(): void
    {
        $em = $this->getEntityManager();
        $users = $em->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            $em->remove($user);
        }
        $em->flush();
    }
}
