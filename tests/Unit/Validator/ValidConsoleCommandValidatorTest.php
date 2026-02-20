<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\ValidConsoleCommand;
use App\Validator\ValidConsoleCommandValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidConsoleCommandValidatorTest extends TestCase
{
    private ValidConsoleCommandValidator $validator;
    private Application $application;

    protected function setUp(): void
    {
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getEnvironment')->willReturn('test');

        $this->validator = new ValidConsoleCommandValidator($kernel);

        $this->application = $this->createMock(Application::class);

        $ref = new \ReflectionClass($this->validator);
        $prop = $ref->getProperty('application');
        $prop->setValue($this->validator, $this->application);
    }

    public function testValidCommandNoViolation(): void
    {
        $this->application->method('find')->willReturn(new Command('app:test-command'));

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->validator->initialize($context);
        $this->validator->validate('app:test-command', new ValidConsoleCommand());
    }

    public function testNullValueNoViolation(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->validator->initialize($context);
        $this->validator->validate(null, new ValidConsoleCommand());
    }

    public function testEmptyStringNoViolation(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->validator->initialize($context);
        $this->validator->validate('', new ValidConsoleCommand());
    }

    public function testInvalidCommandAddsViolation(): void
    {
        $this->application->method('find')
            ->willThrowException(new CommandNotFoundException('Command not found'));

        $constraint = new ValidConsoleCommand();

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setParameter')
            ->with('{{ command }}', 'nonexistent:command')
            ->willReturn($builder);
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($builder);

        $this->validator->initialize($context);
        $this->validator->validate('nonexistent:command', $constraint);
    }

    public function testExtractsCommandNameFromArguments(): void
    {
        $this->application->method('find')
            ->willReturn(new Command('app:test-command'));

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->validator->initialize($context);
        $this->validator->validate('app:test-command --verbose', new ValidConsoleCommand());
    }

    public function testWrongConstraintTypeThrowsException(): void
    {
        $context = $this->createStub(ExecutionContextInterface::class);
        $this->validator->initialize($context);

        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate('app:test-command', new NotBlank());
    }
}
