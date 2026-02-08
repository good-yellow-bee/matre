<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TestEnvironment;
use App\Entity\TestSuite;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TestFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        if ('dev' !== $env && 'test' !== $env) {
            return;
        }

        // Test environments
        $staging = new TestEnvironment();
        $staging->setName('Staging US');
        $staging->setCode('staging');
        $staging->setRegion('US');
        $staging->setBaseUrl('https://staging.example.com');
        $staging->setBackendName('admin');
        $staging->setAdminUsername('admin');
        $staging->setAdminPassword('Admin123!');
        $staging->setDescription('Staging environment for US region');
        $manager->persist($staging);

        $preprod = new TestEnvironment();
        $preprod->setName('Pre-Production EU');
        $preprod->setCode('preprod');
        $preprod->setRegion('EU');
        $preprod->setBaseUrl('https://preprod.example.com');
        $preprod->setBackendName('admin');
        $preprod->setAdminUsername('admin');
        $preprod->setAdminPassword('Admin123!');
        $preprod->setIsActive(false);
        $preprod->setDescription('Pre-production EU environment');
        $manager->persist($preprod);

        // Test suites
        $mftfSuite = new TestSuite();
        $mftfSuite->setName('MFTF Smoke Tests');
        $mftfSuite->setType(TestSuite::TYPE_MFTF_GROUP);
        $mftfSuite->setTestPattern('smoke');
        $mftfSuite->setDescription('MFTF smoke test group');
        $mftfSuite->addEnvironment($staging);
        $manager->persist($mftfSuite);

        $playwrightSuite = new TestSuite();
        $playwrightSuite->setName('Playwright E2E Suite');
        $playwrightSuite->setType(TestSuite::TYPE_PLAYWRIGHT_GROUP);
        $playwrightSuite->setTestPattern('@e2e');
        $playwrightSuite->setDescription('Playwright end-to-end tests');
        $playwrightSuite->addEnvironment($staging);
        $manager->persist($playwrightSuite);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            SettingsFixtures::class,
        ];
    }
}
