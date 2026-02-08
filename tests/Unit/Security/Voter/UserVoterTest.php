<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\User;
use App\Security\Voter\UserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserVoterTest extends TestCase
{
    private UserVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new UserVoter();
    }

    public function testAdminCanViewAnyUser(): void
    {
        $admin = $this->createUserStub('admin@test.com', ['ROLE_ADMIN', 'ROLE_USER']);
        $target = $this->createUserStub('other@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($admin), $target, [UserVoter::VIEW]),
        );
    }

    public function testUserCanViewOwnProfile(): void
    {
        $user = $this->createUserStub('user@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($user), $user, [UserVoter::VIEW]),
        );
    }

    public function testUserCannotViewOtherProfile(): void
    {
        $user = $this->createUserStub('user@test.com', ['ROLE_USER']);
        $other = $this->createUserStub('other@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken($user), $other, [UserVoter::VIEW]),
        );
    }

    public function testAdminCanEditAnyUser(): void
    {
        $admin = $this->createUserStub('admin@test.com', ['ROLE_ADMIN', 'ROLE_USER']);
        $target = $this->createUserStub('other@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($admin), $target, [UserVoter::EDIT]),
        );
    }

    public function testUserCanEditOwnProfile(): void
    {
        $user = $this->createUserStub('user@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($user), $user, [UserVoter::EDIT]),
        );
    }

    public function testUserCannotEditOtherProfile(): void
    {
        $user = $this->createUserStub('user@test.com', ['ROLE_USER']);
        $other = $this->createUserStub('other@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken($user), $other, [UserVoter::EDIT]),
        );
    }

    public function testAdminCanDeleteOtherUser(): void
    {
        $admin = $this->createUserStub('admin@test.com', ['ROLE_ADMIN', 'ROLE_USER']);
        $target = $this->createUserStub('other@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($admin), $target, [UserVoter::DELETE]),
        );
    }

    public function testAdminCannotDeleteSelf(): void
    {
        $admin = $this->createUserStub('admin@test.com', ['ROLE_ADMIN', 'ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken($admin), $admin, [UserVoter::DELETE]),
        );
    }

    public function testUserCannotDeleteAnyone(): void
    {
        $user = $this->createUserStub('user@test.com', ['ROLE_USER']);
        $other = $this->createUserStub('other@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken($user), $other, [UserVoter::DELETE]),
        );
    }

    public function testAdminCanCreateUser(): void
    {
        $admin = $this->createUserStub('admin@test.com', ['ROLE_ADMIN', 'ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->createToken($admin), null, [UserVoter::CREATE]),
        );
    }

    public function testUserCannotCreateUser(): void
    {
        $user = $this->createUserStub('user@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->createToken($user), null, [UserVoter::CREATE]),
        );
    }

    public function testUnsupportedAttributeAbstains(): void
    {
        $user = $this->createUserStub('user@test.com', ['ROLE_USER']);
        $target = $this->createUserStub('other@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($this->createToken($user), $target, ['UNKNOWN_ATTR']),
        );
    }

    public function testNotLoggedInDenied(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $target = $this->createUserStub('other@test.com', ['ROLE_USER']);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $target, [UserVoter::VIEW]),
        );
    }

    private function createToken(UserInterface $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function createUserStub(string $identifier, array $roles): User
    {
        $user = $this->createStub(User::class);
        $user->method('getUserIdentifier')->willReturn($identifier);
        $user->method('getRoles')->willReturn($roles);

        return $user;
    }
}
