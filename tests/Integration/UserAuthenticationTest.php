<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Integration test for user authentication: login → 2FA → session management.
 */
class UserAuthenticationTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager = null;

    private ?UserPasswordHasherInterface $passwordHasher = null;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        $this->passwordHasher = null;
        parent::tearDown();
    }

    // =====================
    // Login Flow
    // =====================

    public function testSuccessfulLoginRedirects(): void
    {
        $client = static::createClient();
        $user = $this->createUser('logintest', 'logintest@example.com', 'Password123!');

        $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Sign in', [
            '_username' => $user->getUsername(),
            '_password' => 'Password123!',
        ]);

        $this->assertResponseRedirects();
    }

    public function testInvalidPasswordShowsError(): void
    {
        $client = static::createClient();
        $user = $this->createUser('badpass', 'badpass@example.com', 'Password123!');

        $client->request('GET', '/login');
        $client->submitForm('Sign in', [
            '_username' => $user->getUsername(),
            '_password' => 'WrongPassword!',
        ]);

        $this->assertResponseRedirects('/login');
    }

    public function testNonexistentUserShowsError(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');
        $client->submitForm('Sign in', [
            '_username' => 'doesnotexist',
            '_password' => 'Password123!',
        ]);

        $this->assertResponseRedirects('/login');
    }

    public function testDisabledUserCannotLogin(): void
    {
        $client = static::createClient();
        $user = $this->createUser('disauth', 'disauth@example.com', 'Password123!');
        $user->setIsActive(false);
        $this->getEntityManager()->flush();

        $client->request('GET', '/login');
        $client->submitForm('Sign in', [
            '_username' => $user->getUsername(),
            '_password' => 'Password123!',
        ]);

        $this->assertResponseRedirects('/login');
    }

    // =====================
    // Admin Access Control
    // =====================

    public function testUnauthenticatedUserRedirectedFromAdmin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/');
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login', $client->getResponse()->headers->get('Location'));
    }

    public function testAuthenticatedUserAccessesAdmin(): void
    {
        $client = static::createClient();
        $user = $this->createUser('adminaccess', 'adminaccess@example.com', 'Password123!');
        $client->loginUser($user);

        $client->request('GET', '/admin/');
        $response = $client->getResponse();
        $this->assertTrue(
            $response->isSuccessful() || $response->isRedirection(),
            'Authenticated user should access /admin',
        );
    }

    public function testAdminRoleAccessesAdminPages(): void
    {
        $client = static::createClient();
        $admin = $this->createUser('adminrole', 'adminrole@example.com', 'Password123!', ['ROLE_ADMIN']);
        $client->loginUser($admin);

        $client->request('GET', '/admin/');
        $this->assertTrue(
            $client->getResponse()->isSuccessful() || $client->getResponse()->isRedirection(),
        );
    }

    // =====================
    // 2FA Configuration
    // =====================

    public function testTotpSecretCanBeSetOnUser(): void
    {
        $client = static::createClient();
        $user = $this->createUser('totp_user', 'totp_user@example.com', 'Password123!');
        $em = $this->getEntityManager();

        $this->assertNull($user->getTotpSecret());
        $this->assertFalse($user->isTotpEnabled());

        $user->setTotpSecret('JBSWY3DPEHPK3PXP');
        $user->setIsTotpEnabled(true);
        $em->flush();

        $em->clear();
        $reloaded = $em->find(User::class, $user->getId());
        $this->assertSame('JBSWY3DPEHPK3PXP', $reloaded->getTotpSecret());
        $this->assertTrue($reloaded->isTotpEnabled());
    }

    public function testTwoFactorSetupPageRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/2fa-setup');
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login', $client->getResponse()->headers->get('Location'));
    }

    // =====================
    // User Entity
    // =====================

    public function testPasswordHashingWithBcrypt(): void
    {
        $client = static::createClient();
        $hasher = $this->getPasswordHasher();

        $user = new User();
        $user->setUsername('hashtest');
        $user->setEmail('hashtest@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);

        $hash = $hasher->hashPassword($user, 'TestPassword123!');
        $user->setPassword($hash);

        $this->assertTrue($hasher->isPasswordValid($user, 'TestPassword123!'));
        $this->assertFalse($hasher->isPasswordValid($user, 'WrongPassword'));
        $this->assertStringStartsWith('$2', $hash);
    }

    public function testUserRoleManagement(): void
    {
        $client = static::createClient();
        $user = $this->createUser('roletest', 'roletest@example.com', 'Password123!');
        $em = $this->getEntityManager();

        $this->assertContains('ROLE_USER', $user->getRoles());

        $user->addRole('ROLE_ADMIN');
        $em->flush();

        $em->clear();
        $reloaded = $em->find(User::class, $user->getId());
        $this->assertContains('ROLE_ADMIN', $reloaded->getRoles());
        $this->assertContains('ROLE_USER', $reloaded->getRoles());

        $reloaded->removeRole('ROLE_ADMIN');
        $em->flush();

        $em->clear();
        $final = $em->find(User::class, $reloaded->getId());
        $this->assertNotContains('ROLE_ADMIN', $final->getRoles());
        $this->assertContains('ROLE_USER', $final->getRoles());
    }

    public function testUserEnabledState(): void
    {
        $client = static::createClient();
        $user = $this->createUser('enabletest', 'enabletest@example.com', 'Password123!');
        $em = $this->getEntityManager();

        $this->assertTrue($user->isEnabled());

        $user->setIsActive(false);
        $em->flush();

        $em->clear();
        $reloaded = $em->find(User::class, $user->getId());
        $this->assertFalse($reloaded->isEnabled());
    }

    // =====================
    // Session
    // =====================

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
    }

    public function testLogoutRedirects(): void
    {
        $client = static::createClient();
        $user = $this->createUser('logouttest', 'logouttest@example.com', 'Password123!');
        $client->loginUser($user);

        $client->request('GET', '/logout');
        $this->assertResponseRedirects();
    }

    // =====================
    // Notification Preferences
    // =====================

    public function testUserNotificationPreferences(): void
    {
        $client = static::createClient();
        $user = $this->createUser('notifpref', 'notifpref@example.com', 'Password123!');
        $em = $this->getEntityManager();

        $user->setNotificationsEnabled(true);
        $user->setNotifyByEmail(true);
        $user->setNotifyBySlack(false);
        $em->flush();

        $em->clear();
        $reloaded = $em->find(User::class, $user->getId());
        $this->assertTrue($reloaded->isNotificationsEnabled());
        $this->assertTrue($reloaded->isNotifyByEmail());
        $this->assertFalse($reloaded->isNotifyBySlack());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        if (null === $this->entityManager) {
            $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        }

        return $this->entityManager;
    }

    private function getPasswordHasher(): UserPasswordHasherInterface
    {
        if (null === $this->passwordHasher) {
            $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        }

        return $this->passwordHasher;
    }

    // =====================
    // Helpers
    // =====================

    private function createUser(
        string $username,
        string $email,
        string $password,
        array $roles = ['ROLE_USER'],
    ): User {
        $em = $this->getEntityManager();
        $hasher = $this->getPasswordHasher();
        $suffix = substr(uniqid(), -6);

        $user = new User();
        $user->setUsername($username . '_' . $suffix);
        $user->setEmail(str_replace('@', '_' . $suffix . '@', $email));
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles($roles);
        $user->setIsActive(true);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
