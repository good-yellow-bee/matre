<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for User entity.
 *
 * Tests user authentication, roles, and TOTP 2FA.
 */
class UserTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
        $this->assertTrue($user->getIsActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertNull($user->getUpdatedAt());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    public function testUsernameGetterAndSetter(): void
    {
        $user = new User();
        $user->setUsername('testuser');

        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('testuser', $user->getUserIdentifier());
    }

    public function testEmailGetterAndSetter(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->assertEquals('test@example.com', $user->getEmail());
    }

    public function testPasswordGetterAndSetter(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');

        $this->assertEquals('hashed_password', $user->getPassword());
    }

    public function testPlainPasswordHandling(): void
    {
        $user = new User();
        $user->setPlainPassword('plaintext');

        $this->assertEquals('plaintext', $user->getPlainPassword());

        $user->eraseCredentials();

        $this->assertNull($user->getPlainPassword());
    }

    public function testRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();
        $user->setRoles([]);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    public function testAddRole(): void
    {
        $user = new User();
        $user->addRole('ROLE_ADMIN');

        $this->assertTrue($user->hasRole('ROLE_ADMIN'));
        $this->assertTrue($user->hasRole('ROLE_USER'));
    }

    public function testAddRoleDoesNotDuplicate(): void
    {
        $user = new User();
        $user->addRole('ROLE_ADMIN');
        $user->addRole('ROLE_ADMIN');

        $roles = array_filter($user->getRoles(), fn ($role) => 'ROLE_ADMIN' === $role);
        $this->assertCount(1, $roles);
    }

    public function testRemoveRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_EDITOR']);

        $user->removeRole('ROLE_ADMIN');

        $this->assertFalse($user->hasRole('ROLE_ADMIN'));
        $this->assertTrue($user->hasRole('ROLE_EDITOR'));
        $this->assertTrue($user->hasRole('ROLE_USER'));
    }

    public function testRemoveRoleReindexesArray(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN', 'ROLE_EDITOR']);
        $user->removeRole('ROLE_ADMIN');

        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('roles');
        $internalRoles = $property->getValue($user);

        // Check that array is re-indexed (keys are sequential)
        $this->assertEquals(array_values($internalRoles), $internalRoles);
    }

    public function testHasRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $this->assertTrue($user->hasRole('ROLE_ADMIN'));
        $this->assertTrue($user->hasRole('ROLE_USER'));
        $this->assertFalse($user->hasRole('ROLE_SUPER_ADMIN'));
    }

    public function testIsActiveGetterAndSetter(): void
    {
        $user = new User();

        $this->assertTrue($user->getIsActive());
        $this->assertTrue($user->isEnabled());

        $user->setIsActive(false);

        $this->assertFalse($user->getIsActive());
        $this->assertFalse($user->isEnabled());
    }

    public function testCreatedAtGetterAndSetter(): void
    {
        $user = new User();
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');
        $user->setCreatedAt($date);

        $this->assertEquals($date, $user->getCreatedAt());
    }

    public function testUpdatedAtInitiallyNull(): void
    {
        $user = new User();

        $this->assertNull($user->getUpdatedAt());
    }

    public function testSetUpdatedAt(): void
    {
        $user = new User();
        $before = new \DateTimeImmutable();

        $user->setUpdatedAt();

        $after = new \DateTimeImmutable();

        $this->assertNotNull($user->getUpdatedAt());
        $this->assertGreaterThanOrEqual($before, $user->getUpdatedAt());
        $this->assertLessThanOrEqual($after, $user->getUpdatedAt());
    }

    public function testToString(): void
    {
        $user = new User();
        $user->setUsername('john');
        $user->setEmail('john@example.com');

        $this->assertEquals('john (john@example.com)', (string) $user);
    }

    public function testFluentInterface(): void
    {
        $user = new User();

        $result = $user
            ->setUsername('test')
            ->setEmail('test@example.com')
            ->setPassword('hashed')
            ->setIsActive(true)
            ->addRole('ROLE_ADMIN');

        $this->assertSame($user, $result);
    }

    public function testTotpConfiguration(): void
    {
        $user = new User();

        $this->assertFalse($user->isTotpEnabled());
        $this->assertNull($user->getTotpSecret());
        $this->assertFalse($user->isTotpAuthenticationEnabled());

        $user->setTotpSecret('TESTSECRET123');
        $user->setIsTotpEnabled(true);

        $this->assertTrue($user->isTotpEnabled());
        $this->assertEquals('TESTSECRET123', $user->getTotpSecret());
        $this->assertTrue($user->isTotpAuthenticationEnabled());
    }
}
