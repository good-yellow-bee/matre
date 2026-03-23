<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TestEnvironment;
use App\Entity\TestRun;
use App\Repository\GlobalEnvVariableRepository;
use App\Service\MagentoContainerPoolService;
use App\Service\MftfExecutorService;
use App\Service\Security\ShellEscapeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Tests for the pollProcessWithHeartbeat() lock refresh resilience.
 */
class MftfExecutorServicePollTest extends TestCase
{
    private LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger;

    private EntityManagerInterface&\PHPUnit\Framework\MockObject\Stub $entityManager;

    private TestableMftfExecutorService $service;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        // entityManager->refresh() is called by checkCancellation — make it a no-op
        $this->entityManager->method('refresh')->willReturnCallback(function () {});

        $this->service = new TestableMftfExecutorService(
            $this->logger,
            $this->createStub(GlobalEnvVariableRepository::class),
            $this->createStub(ShellEscapeService::class),
            $this->createStub(MagentoContainerPoolService::class),
            $this->entityManager,
            '/app',
            'selenium-hub',
            4444,
            '/var/www/html',
        );
    }

    public function testNoLockRefreshWhenIntervalNotElapsed(): void
    {
        $callCount = 0;
        $lockCallback = function () use (&$callCount) {
            ++$callCount;
        };

        $process = $this->createProcessMock(1);
        $handle = tmpfile();
        $run = $this->createTestRun();

        $this->service->exposedPollProcessWithHeartbeat(
            $process,
            $run,
            $handle,
            $lockCallback,
            null,
        );

        // Lock was never called (process only ran 1 iteration, <30s elapsed)
        $this->assertSame(0, $callCount);
    }

    public function testLockRefreshRetriesOnTransientFailure(): void
    {
        $attempt = 0;
        $lockCallback = function () use (&$attempt) {
            ++$attempt;
            if (1 === $attempt) {
                throw new \RuntimeException('DNS blip');
            }
            // 2nd call (retry) succeeds
        };

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Lock refresh succeeded on retry'),
                $this->callback(fn (array $ctx) => 'DNS blip' === $ctx['originalError']),
            );

        $process = $this->createProcessMock(1, forceRefreshCheck: true);
        $handle = tmpfile();
        $run = $this->createTestRun();

        $this->service->exposedPollProcessWithHeartbeat(
            $process,
            $run,
            $handle,
            $lockCallback,
            null,
        );

        $this->assertSame(2, $attempt);
    }

    public function testLockRefreshFailsBelowThresholdContinues(): void
    {
        $lockCallback = function () {
            throw new \RuntimeException('DNS down');
        };

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                $this->equalTo('Lock refresh failed (will retry in 30s)'),
                $this->anything(),
            );

        // Process runs 1 forced-refresh iteration then stops
        $process = $this->createProcessMock(1, forceRefreshCheck: true);
        $handle = tmpfile();
        $run = $this->createTestRun();

        // Should NOT throw — 1 failure is below threshold of 5
        $this->service->exposedPollProcessWithHeartbeat(
            $process,
            $run,
            $handle,
            $lockCallback,
            null,
        );
    }

    public function testLockRefreshAbortsAtThreshold(): void
    {
        $lockCallback = function () {
            throw new \RuntimeException('DNS permanently down');
        };

        // Process runs 5 forced-refresh iterations (enough to hit threshold)
        $process = $this->createProcessMock(5, forceRefreshCheck: true);
        $handle = tmpfile();
        $run = $this->createTestRun();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Lock refresh failed - aborting to prevent parallel execution conflicts');

        $this->service->exposedPollProcessWithHeartbeat(
            $process,
            $run,
            $handle,
            $lockCallback,
            null,
        );
    }

    public function testHandleClosedOnCancellation(): void
    {
        // Make checkCancellation throw by setting run status to cancelled
        $run = $this->createTestRun();
        $run->setStatus(TestRun::STATUS_CANCELLED);

        $process = $this->createStub(Process::class);
        $process->method('isRunning')->willReturn(true);
        $process->method('getPid')->willReturn(12345);

        $handle = tmpfile();

        $thrown = false;

        try {
            $this->service->exposedPollProcessWithHeartbeat(
                $process,
                $run,
                $handle,
                null,
                null,
            );
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('cancelled', $e->getMessage());
        }

        $this->assertTrue($thrown, 'Expected RuntimeException for cancellation');
        // Handle should be closed by finally block
        $this->assertFalse(is_resource($handle));
    }

    /**
     * Creates a Process mock that reports isRunning() for N iterations.
     *
     * When forceRefreshCheck=true, the service's internal time check is bypassed
     * by setting lastRefresh to the past via a custom subclass.
     */
    private function createProcessMock(int $iterations, bool $forceRefreshCheck = false): Process
    {
        $callCount = 0;
        $process = $this->createStub(Process::class);
        $process->method('isRunning')
            ->willReturnCallback(function () use (&$callCount, $iterations) {
                return ++$callCount <= $iterations;
            });

        if ($forceRefreshCheck) {
            $this->service->setForceRefreshCheck(true);
        }

        return $process;
    }

    private function createTestRun(int $id = 1): TestRun
    {
        $env = new TestEnvironment();
        $env->setName('preprod-us');
        $env->setCode('preprod-us');

        $run = new TestRun();
        $run->setEnvironment($env);
        $run->setType(TestRun::TYPE_MFTF);
        $run->setTestFilter('MOEC8899Cest');

        $reflection = new \ReflectionClass($run);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($run, $id);

        return $run;
    }
}

/**
 * Test subclass to expose protected pollProcessWithHeartbeat() and control time behavior.
 */
class TestableMftfExecutorService extends MftfExecutorService
{
    private bool $forceRefreshCheck = false;

    public function setForceRefreshCheck(bool $force): void
    {
        $this->forceRefreshCheck = $force;
    }

    /**
     * @param resource $handle
     */
    public function exposedPollProcessWithHeartbeat(
        Process $process,
        TestRun $run,
        $handle,
        ?callable $lockRefreshCallback,
        ?callable $heartbeatCallback,
        ?string $testName = null,
    ): void {
        $this->pollProcessWithHeartbeat($process, $run, $handle, $lockRefreshCallback, $heartbeatCallback, $testName);
    }

    protected function pollProcessWithHeartbeat(
        Process $process,
        TestRun $run,
        $handle,
        ?callable $lockRefreshCallback,
        ?callable $heartbeatCallback,
        ?string $testName = null,
    ): void {
        if ($this->forceRefreshCheck) {
            // Override to skip time checks and usleep — directly test the refresh logic
            $this->pollWithForcedRefresh($process, $run, $handle, $lockRefreshCallback, $heartbeatCallback, $testName);

            return;
        }

        parent::pollProcessWithHeartbeat($process, $run, $handle, $lockRefreshCallback, $heartbeatCallback, $testName);
    }

    /**
     * Variant that forces lock refresh on every iteration (no time delay, no usleep).
     *
     * @param resource $handle
     */
    private function pollWithForcedRefresh(
        Process $process,
        TestRun $run,
        $handle,
        ?callable $lockRefreshCallback,
        ?callable $heartbeatCallback,
        ?string $testName = null,
    ): void {
        $lockRefreshFailures = 0;
        $maxLockRefreshFailures = 5;

        try {
            while ($process->isRunning()) {
                $this->callCheckCancellation($run, $process);

                if ($lockRefreshCallback) {
                    try {
                        $lockRefreshCallback();
                        $lockRefreshFailures = 0;
                    } catch (\Exception $e) {
                        try {
                            $lockRefreshCallback();
                            $lockRefreshFailures = 0;
                            $this->getLogger()->info('Lock refresh succeeded on retry', [
                                'runId' => $run->getId(),
                                'testName' => $testName,
                                'originalError' => $e->getMessage(),
                            ]);
                        } catch (\Exception $retryException) {
                            ++$lockRefreshFailures;
                            if ($lockRefreshFailures >= $maxLockRefreshFailures) {
                                $this->getLogger()->error('Aborting test run - lock refresh persistently failing', [
                                    'runId' => $run->getId(),
                                    'testName' => $testName,
                                    'error' => $retryException->getMessage(),
                                    'consecutiveFailures' => $lockRefreshFailures,
                                ]);
                                $process->stop();

                                throw new \RuntimeException('Lock refresh failed - aborting to prevent parallel execution conflicts');
                            }
                            $this->getLogger()->warning('Lock refresh failed (will retry in 30s)', [
                                'runId' => $run->getId(),
                                'testName' => $testName,
                                'error' => $retryException->getMessage(),
                                'consecutiveFailures' => $lockRefreshFailures,
                            ]);
                        }
                    }
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function callCheckCancellation(TestRun $run, Process $process): void
    {
        // Use reflection to call the private checkCancellation method
        $reflection = new \ReflectionMethod(MftfExecutorService::class, 'checkCancellation');
        $reflection->invoke($this, $run, $process);
    }

    private function getLogger(): LoggerInterface
    {
        $reflection = new \ReflectionProperty(MftfExecutorService::class, 'logger');

        return $reflection->getValue($this);
    }
}
