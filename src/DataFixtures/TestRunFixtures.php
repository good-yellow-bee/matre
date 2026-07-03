<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TestEnvironment;
use App\Entity\TestResult;
use App\Entity\TestRun;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds one completed failed run so e2e tests can exercise the run list/detail UI.
 */
class TestRunFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        if ('dev' !== $env && 'test' !== $env) {
            return;
        }

        $environment = $manager->getRepository(TestEnvironment::class)->findOneBy(['code' => 'staging']);
        if (!$environment instanceof TestEnvironment) {
            return;
        }

        $run = new TestRun();
        $run->setEnvironment($environment);
        $run->setType('mftf');
        $run->setStatus('failed');
        $run->setTestFilter('FixtureSmokeTest');
        $run->setTriggeredBy('manual');
        $run->setSendNotifications(false);
        $run->setStartedAt(new \DateTimeImmutable('-1 hour'));
        $run->setCompletedAt(new \DateTimeImmutable('-58 minutes'));
        $run->setProgress(1, 1);
        $run->setErrorMessage('All 1 test(s) failed');
        $run->setOutput("=== MFTF Output ===\n\nGenerate Tests Command Run\n\nFixtureSmokeTest: Seeded fixture run\n Fail  Element \".fixture-probe\" was not found.\n\nFAILURES!\nTests: 1, Assertions: 0, Failures: 1.");
        $manager->persist($run);

        $result = new TestResult();
        $result->setTestRun($run);
        $result->setTestName('FixtureSmokeTest: Seeded fixture run');
        $result->setTestId('FixtureSmokeTest');
        $result->setStatus('failed');
        $result->setDuration(12.3);
        $result->setErrorMessage('Element ".fixture-probe" was not found.');
        $manager->persist($result);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TestFixtures::class];
    }
}
