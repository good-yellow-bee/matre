<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\ValidConsoleCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;

class ValidConsoleCommandTest extends TestCase
{
    public function testDefaultMessageContainsDoesNotExist(): void
    {
        $constraint = new ValidConsoleCommand();

        $this->assertStringContainsString('does not exist', $constraint->message);
    }

    public function testIsConstraintSubclass(): void
    {
        $constraint = new ValidConsoleCommand();

        $this->assertInstanceOf(Constraint::class, $constraint);
    }
}
