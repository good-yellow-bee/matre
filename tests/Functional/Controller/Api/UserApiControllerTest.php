<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\User;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for UserApiController.
 */
class UserApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/users';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testListRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', self::BASE_URL);

        $this->assertResponseRedirects('/login');
    }

    public function testListRequiresAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', self::BASE_URL);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testListSucceedsWithAdminRole(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);

        $this->assertJsonResponse($response, 200);
    }

    // =====================
    // List (index) Tests
    // =====================

    public function testListReturnsUserData(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['items']);
        $this->assertArrayHasKey('total', $data['meta']);
    }

    public function testListSupportsPagination(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '?page=1&limit=5');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertLessThanOrEqual(5, count($data['items']));
        $this->assertArrayHasKey('page', $data['meta']);
        $this->assertArrayHasKey('limit', $data['meta']);
        $this->assertEquals(1, $data['meta']['page']);
        $this->assertEquals(5, $data['meta']['limit']);
    }

    public function testListSupportsSearch(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));
        $this->createUser("searchable_{$suffix}", "searchable_{$suffix}@test.com");

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . "?q=searchable_{$suffix}");
        $data = $this->assertJsonResponse($response, 200);

        $this->assertCount(1, $data['items']);
        $this->assertStringContainsString("searchable_{$suffix}", $data['items'][0]['username']);
    }

    // =====================
    // Get Single User Tests
    // =====================

    public function testGetUserReturnsUserData(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $user->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($user->getUsername(), $data['username']);
        $this->assertEquals($user->getEmail(), $data['email']);
    }

    public function testGetUserReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/99999');

        $this->assertJsonError($response, 404, 'not found');
    }

    // =====================
    // Create User Tests
    // =====================

    public function testCreateUserSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'username' => "newuser_{$suffix}",
            'email' => "new_{$suffix}@test.com",
            'password' => 'Password123!',
            'roles' => ['ROLE_USER'],
        ]);

        $data = $this->assertJsonResponse($response, 201);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('id', $data);

        // Verify user exists in database
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->find($data['id']);
        $this->assertNotNull($user);
        $this->assertEquals("newuser_{$suffix}", $user->getUsername());
    }

    public function testCreateUserValidatesRequiredFields(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // Send only username to trigger email/password validation
        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'username' => 'validuser',
        ]);

        $data = $this->assertJsonResponse($response, 422);
        $this->assertArrayHasKey('errors', $data);
        // Email required, password required
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testCreateUserValidatesPasswordComplexity(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'username' => "newuser_{$suffix}",
            'email' => "new_{$suffix}@test.com",
            'password' => 'simple', // Too simple - no uppercase, no number
        ]);

        $data = $this->assertJsonResponse($response, 422);
        $this->assertArrayHasKey('password', $data['errors']);
    }

    public function testCreateUserRejectsDuplicateUsername(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $existingUser = $this->createUser();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'username' => $existingUser->getUsername(),
            'email' => 'new_unique_' . bin2hex(random_bytes(4)) . '@test.com',
            'password' => 'Password123!',
        ]);

        $data = $this->assertJsonResponse($response, 422);
        $this->assertArrayHasKey('username', $data['errors']);
    }

    public function testCreateUserRejectsDuplicateEmail(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $existingUser = $this->createUser();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'username' => 'unique_newuser_' . bin2hex(random_bytes(4)),
            'email' => $existingUser->getEmail(),
            'password' => 'Password123!',
        ]);

        $data = $this->assertJsonResponse($response, 422);
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testCreateUserRejectsInvalidRoles(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL, [
            'username' => "newuser_{$suffix}",
            'email' => "new_{$suffix}@test.com",
            'password' => 'Password123!',
            'roles' => ['ROLE_SUPER_ADMIN'], // Not in ALLOWED_ROLES
        ]);

        $data = $this->assertJsonResponse($response, 422);
        $this->assertArrayHasKey('roles', $data['errors']);
    }

    // =====================
    // Update User Tests
    // =====================

    public function testUpdateUserSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();
        $suffix = bin2hex(random_bytes(4));
        $newUsername = "updated_{$suffix}";
        $newEmail = "updated_{$suffix}@test.com";

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/' . $user->getId(), [
            'username' => $newUsername,
            'email' => $newEmail,
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['success']);

        $this->getEntityManager()->refresh($user);
        $this->assertEquals($newUsername, $user->getUsername());
        $this->assertEquals($newEmail, $user->getEmail());
    }

    public function testUpdateUserCanChangePassword(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();
        $oldHash = $user->getPassword();

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/' . $user->getId(), [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'password' => 'NewPassword123!',
        ]);

        $this->assertJsonResponse($response, 200);
        $this->getEntityManager()->refresh($user);
        $this->assertNotEquals($oldHash, $user->getPassword());
    }

    public function testUpdateUserPreventsSelfModification(): void
    {
        $client = self::createClient();
        $admin = $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/' . $admin->getId(), [
            'username' => $admin->getUsername(),
            'email' => $admin->getEmail(),
            'isActive' => false,
        ]);

        $this->assertJsonError($response, 400, 'cannot modify your own');
    }

    // =====================
    // Delete User Tests
    // =====================

    public function testDeleteUserSucceeds(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUser();
        $userId = $user->getId();

        $response = $this->jsonRequest($client, 'DELETE', self::BASE_URL . '/' . $userId);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['success']);

        $deleted = $this->getEntityManager()->getRepository(User::class)->find($userId);
        $this->assertNull($deleted);
    }

    public function testDeleteUserPreventsSelfDeletion(): void
    {
        $client = self::createClient();
        $admin = $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'DELETE', self::BASE_URL . '/' . $admin->getId());

        $this->assertJsonError($response, 400, 'cannot delete your own');
    }

    public function testDeleteUserReturns404ForNonExistent(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        // Uses ParamConverter which returns Symfony's 404, not JSON
        $this->jsonRequest($client, 'DELETE', self::BASE_URL . '/99999');

        $this->assertResponseStatusCodeSame(404);
    }

    // =====================
    // Validation Endpoint Tests
    // =====================

    public function testValidateUsernameAvailable(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-username', [
            'username' => "unique_{$suffix}",
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['valid']);
    }

    public function testValidateUsernameTaken(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $existingUser = $this->createUser();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-username', [
            'username' => $existingUser->getUsername(),
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['valid']);
    }

    public function testValidateEmailAvailable(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $suffix = bin2hex(random_bytes(4));

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-email', [
            'email' => "newemail_{$suffix}@test.com",
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertTrue($data['valid']);
    }

    public function testValidateEmailTaken(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $existingUser = $this->createUser();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-email', [
            'email' => $existingUser->getEmail(),
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['valid']);
    }

    public function testValidateUsernameInvalidFormat(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-username', [
            'username' => 'invalid username!', // Contains space and special char
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['valid']);
    }

    public function testValidateEmailInvalidFormat(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/validate-email', [
            'email' => 'not-an-email',
        ]);

        $data = $this->assertJsonResponse($response, 200);
        $this->assertFalse($data['valid']);
    }
}
