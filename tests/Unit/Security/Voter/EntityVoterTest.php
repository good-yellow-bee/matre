<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Settings;
use App\Security\Voter\EntityVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class EntityVoterTest extends TestCase
{
    private EntityVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new EntityVoter();
    }

    public function testAdminCanViewSettings(): void
    {
        $admin = $this->createUserStub(['ROLE_ADMIN', 'ROLE_USER']);
        $settings = new Settings();

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($admin), $settings, [EntityVoter::VIEW]),
        );
    }

    public function testAdminCanEditSettings(): void
    {
        $admin = $this->createUserStub(['ROLE_ADMIN', 'ROLE_USER']);
        $settings = new Settings();

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($admin), $settings, [EntityVoter::EDIT]),
        );
    }

    public function testNonAdminCannotViewSettings(): void
    {
        $user = $this->createUserStub(['ROLE_USER']);
        $settings = new Settings();

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken($user), $settings, [EntityVoter::VIEW]),
        );
    }

    public function testUnsupportedEntityAbstains(): void
    {
        $admin = $this->createUserStub(['ROLE_ADMIN', 'ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->createToken($admin), new \stdClass(), [EntityVoter::VIEW]),
        );
    }

    public function testCreateWithClassNameString(): void
    {
        $admin = $this->createUserStub(['ROLE_ADMIN', 'ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($admin), 'App\Entity\Settings', [EntityVoter::CREATE]),
        );
    }

    public function testCreateWithUnknownClassAbstains(): void
    {
        $admin = $this->createUserStub(['ROLE_ADMIN', 'ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->createToken($admin), 'App\Entity\Unknown', [EntityVoter::CREATE]),
        );
    }

    public function testNotLoggedInDenied(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $settings = new Settings();

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $settings, [EntityVoter::VIEW]),
        );
    }

    private function createToken(UserInterface $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createUserStub(array $roles): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getRoles')->willReturn($roles);

        return $user;
    }
}
