<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\ValidCronExpression;
use App\Validator\ValidCronExpressionValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidCronExpressionValidatorTest extends TestCase
{
    private ValidCronExpressionValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ValidCronExpressionValidator();
    }

    public function testValidExpressionNoViolation(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->validator->initialize($context);
        $this->validator->validate('0 * * * *', new ValidCronExpression());
    }

    public function testNullValueNoViolation(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->validator->initialize($context);
        $this->validator->validate(null, new ValidCronExpression());
    }

    public function testEmptyStringNoViolation(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->validator->initialize($context);
        $this->validator->validate('', new ValidCronExpression());
    }

    public function testInvalidExpressionAddsViolation(): void
    {
        $constraint = new ValidCronExpression();

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')
            ->with('{{ value }}', 'invalid cron')
            ->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($builder);

        $this->validator->initialize($context);
        $this->validator->validate('invalid cron', $constraint);
    }

    public function testWrongConstraintTypeThrowsException(): void
    {
        $context = $this->createStub(ExecutionContextInterface::class);
        $this->validator->initialize($context);

        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate('0 * * * *', new NotBlank());
    }
}
