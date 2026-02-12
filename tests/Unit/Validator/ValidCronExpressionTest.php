<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\ValidCronExpression;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;

class ValidCronExpressionTest extends TestCase
{
    public function testDefaultMessageContainsIsNotValid(): void
    {
        $constraint = new ValidCronExpression();

        $this->assertStringContainsString('is not valid', $constraint->message);
    }

    public function testIsConstraintSubclass(): void
    {
        $constraint = new ValidCronExpression();

        $this->assertInstanceOf(Constraint::class, $constraint);
    }
}
