<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserChecker();
    }

    public function testCheckPreAuthAllowsActiveUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getIsActive')->willReturn(true);

        $this->checker->checkPreAuth($user);
        $this->addToAssertionCount(1);
    }

    public function testCheckPreAuthBlocksDisabledUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getIsActive')->willReturn(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Your account is disabled.');

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPreAuthIgnoresNonUserInterface(): void
    {
        $user = $this->createStub(UserInterface::class);

        $this->checker->checkPreAuth($user);
        $this->addToAssertionCount(1);
    }

    public function testCheckPostAuthDoesNothing(): void
    {
        $user = $this->createStub(User::class);

        $this->checker->checkPostAuth($user);
        $this->addToAssertionCount(1);
    }
}
