<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\CronJob;
use App\Entity\TestEnvironment;
use App\Entity\TestSuite;
use App\Message\CronJobMessage;
use App\Message\ScheduledTestRunMessage;
use App\Repository\CronJobRepository;
use App\Repository\TestSuiteRepository;
use App\Scheduler\CronJobScheduleProvider;
use App\Scheduler\TestRunScheduleProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;

/**
 * Integration test for cron scheduling: schedule → execute → log.
 */
class CronSchedulingTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        parent::tearDown();
    }

    // =====================
    // CronJob Schedule Provider
    // =====================

    public function testCronJobScheduleProviderLoadsActiveJobs(): void
    {
        $job = $this->createCronJob('Test Job ' . uniqid(), '*/5 * * * *', 'app:cleanup-tests', true);
        $this->createCronJob('Inactive Job ' . uniqid(), '0 * * * *', 'app:cleanup-tests', false);

        $repo = static::getContainer()->get(CronJobRepository::class);
        $provider = new CronJobScheduleProvider($repo);

        $schedule = $provider->getSchedule();
        $messages = $schedule->getRecurringMessages();

        $found = false;
        foreach ($messages as $recurringMessage) {
            $context = new MessageContext('test', $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
            foreach ($recurringMessage->getMessages($context) as $inner) {
                if ($inner instanceof CronJobMessage && $inner->cronJobId === $job->getId()) {
                    $found = true;
                }
            }
        }
        $this->assertTrue($found, 'Active cron job should be in schedule');
    }

    public function testCronJobScheduleProviderExcludesInactive(): void
    {
        $inactive = $this->createCronJob('Only Inactive ' . uniqid(), '*/10 * * * *', 'app:cleanup-tests', false);

        $repo = static::getContainer()->get(CronJobRepository::class);
        $provider = new CronJobScheduleProvider($repo);

        $schedule = $provider->getSchedule();
        $messages = $schedule->getRecurringMessages();

        foreach ($messages as $recurringMessage) {
            $context = new MessageContext('test', $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
            foreach ($recurringMessage->getMessages($context) as $inner) {
                if ($inner instanceof CronJobMessage) {
                    $this->assertNotSame($inactive->getId(), $inner->cronJobId);
                }
            }
        }
    }

    // =====================
    // TestRun Schedule Provider
    // =====================

    public function testTestRunScheduleProviderLoadsScheduledSuites(): void
    {
        $env = $this->createTestEnvironment();
        $suite = $this->createTestSuiteWithCron($env, '0 */6 * * *');

        $repo = static::getContainer()->get(TestSuiteRepository::class);
        $provider = new TestRunScheduleProvider($repo);

        $schedule = $provider->getSchedule();
        $messages = $schedule->getRecurringMessages();

        $found = false;
        foreach ($messages as $recurringMessage) {
            $context = new MessageContext('test', $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
            foreach ($recurringMessage->getMessages($context) as $inner) {
                if ($inner instanceof ScheduledTestRunMessage && $inner->suiteId === $suite->getId()) {
                    $found = true;
                }
            }
        }
        $this->assertTrue($found, 'Scheduled suite should be in schedule');
    }

    public function testTestRunScheduleProviderExcludesSuitesWithoutCron(): void
    {
        $env = $this->createTestEnvironment();

        $suite = new TestSuite();
        $suite->setName('No Cron Suite ' . uniqid());
        $suite->setType(TestSuite::TYPE_MFTF_TEST);
        $suite->setTestPattern('MOEC2609');
        $suite->setIsActive(true);
        $suite->addEnvironment($env);
        $this->entityManager->persist($suite);
        $this->entityManager->flush();

        $repo = static::getContainer()->get(TestSuiteRepository::class);
        $provider = new TestRunScheduleProvider($repo);

        $schedule = $provider->getSchedule();
        $messages = $schedule->getRecurringMessages();

        foreach ($messages as $recurringMessage) {
            $context = new MessageContext('test', $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
            foreach ($recurringMessage->getMessages($context) as $inner) {
                if ($inner instanceof ScheduledTestRunMessage) {
                    $this->assertNotSame($suite->getId(), $inner->suiteId);
                }
            }
        }
    }

    // =====================
    // CronJob Entity Lifecycle
    // =====================

    public function testCronJobStatusTracking(): void
    {
        $job = $this->createCronJob('Status Test ' . uniqid(), '*/15 * * * *', 'app:cleanup-tests');

        $this->assertNull($job->getLastRunAt());
        $this->assertNull($job->getLastStatus());
        $this->assertNull($job->getLastOutput());

        $job->setLastRunAt(new \DateTimeImmutable());
        $job->setLastStatus(CronJob::STATUS_RUNNING);
        $this->entityManager->flush();

        $this->assertSame(CronJob::STATUS_RUNNING, $job->getLastStatus());

        $job->setLastStatus(CronJob::STATUS_SUCCESS);
        $job->setLastOutput('Cleaned 5 artifacts');
        $this->entityManager->flush();

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(CronJob::class, $job->getId());
        $this->assertSame(CronJob::STATUS_SUCCESS, $reloaded->getLastStatus());
        $this->assertSame('Cleaned 5 artifacts', $reloaded->getLastOutput());
    }

    public function testCronJobFailureTracking(): void
    {
        $job = $this->createCronJob('Failure Test ' . uniqid(), '*/30 * * * *', 'app:cleanup-tests');

        $job->setLastRunAt(new \DateTimeImmutable());
        $job->setLastStatus(CronJob::STATUS_FAILED);
        $job->setLastOutput('Permission denied');
        $this->entityManager->flush();

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(CronJob::class, $job->getId());
        $this->assertSame(CronJob::STATUS_FAILED, $reloaded->getLastStatus());
        $this->assertSame('Permission denied', $reloaded->getLastOutput());
    }

    public function testCronJobLockStatus(): void
    {
        $job = $this->createCronJob('Lock Test ' . uniqid(), '* * * * *', 'app:cleanup-tests');

        $job->setLastStatus(CronJob::STATUS_LOCKED);
        $this->entityManager->flush();

        $this->assertSame(CronJob::STATUS_LOCKED, $job->getLastStatus());
    }

    // =====================
    // Messages
    // =====================

    public function testCronJobMessageIsReadonly(): void
    {
        $message = new CronJobMessage(42);
        $this->assertSame(42, $message->cronJobId);
    }

    public function testScheduledTestRunMessageIsReadonly(): void
    {
        $message = new ScheduledTestRunMessage(99);
        $this->assertSame(99, $message->suiteId);
    }

    // =====================
    // Repository Queries
    // =====================

    public function testFindActiveReturnsOnlyActive(): void
    {
        $active = $this->createCronJob('Active ' . uniqid(), '*/5 * * * *', 'app:cleanup-tests', true);
        $inactive = $this->createCronJob('Inactive ' . uniqid(), '*/5 * * * *', 'app:cleanup-tests', false);

        $repo = static::getContainer()->get(CronJobRepository::class);
        $results = $repo->findActive();

        $ids = array_map(fn ($j) => $j->getId(), $results);
        $this->assertContains($active->getId(), $ids);
        $this->assertNotContains($inactive->getId(), $ids);
    }

    public function testFindScheduledSuitesReturnsOnlyWithCron(): void
    {
        $env = $this->createTestEnvironment();
        $withCron = $this->createTestSuiteWithCron($env, '0 */12 * * *');

        $noCron = new TestSuite();
        $noCron->setName('No Cron ' . uniqid());
        $noCron->setType(TestSuite::TYPE_MFTF_TEST);
        $noCron->setTestPattern('TEST001');
        $noCron->setIsActive(true);
        $noCron->addEnvironment($env);
        $this->entityManager->persist($noCron);
        $this->entityManager->flush();

        $repo = static::getContainer()->get(TestSuiteRepository::class);
        $results = $repo->findScheduled();

        $ids = array_map(fn ($s) => $s->getId(), $results);
        $this->assertContains($withCron->getId(), $ids);
        $this->assertNotContains($noCron->getId(), $ids);
    }

    // =====================
    // Helpers
    // =====================

    private function createCronJob(string $name, string $cron, string $command, bool $active = true): CronJob
    {
        $job = new CronJob();
        $job->setName($name);
        $job->setCronExpression($cron);
        $job->setCommand($command);
        $job->setIsActive($active);
        $this->entityManager->persist($job);
        $this->entityManager->flush();

        return $job;
    }

    private function createTestEnvironment(): TestEnvironment
    {
        $env = new TestEnvironment();
        $env->setName('Cron Test Env ' . uniqid());
        $env->setCode('cron-' . uniqid());
        $env->setRegion('us-east-1');
        $env->setBaseUrl('https://test.example.com');
        $env->setIsActive(true);
        $this->entityManager->persist($env);
        $this->entityManager->flush();

        return $env;
    }

    private function createTestSuiteWithCron(TestEnvironment $env, string $cronExpression): TestSuite
    {
        $suite = new TestSuite();
        $suite->setName('Scheduled Suite ' . uniqid());
        $suite->setType(TestSuite::TYPE_MFTF_TEST);
        $suite->setTestPattern('MOEC2609');
        $suite->setCronExpression($cronExpression);
        $suite->setIsActive(true);
        $suite->addEnvironment($env);
        $this->entityManager->persist($suite);
        $this->entityManager->flush();

        return $suite;
    }
}
