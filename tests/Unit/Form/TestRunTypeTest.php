<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\TestEnvironment;
use App\Entity\TestSuite;
use App\Form\TestRunType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class TestRunTypeTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer()->get('test.service_container');
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->formFactory = $container->get('form.factory');
    }

    public function testSubmitAcceptsSendNotificationsField(): void
    {
        [$environment, $suite] = $this->createEnvironmentAndSuite();

        $form = $this->formFactory->create(TestRunType::class, null, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'suite' => (string) $suite->getId(),
            'environment' => (string) $environment->getId(),
            'sendNotifications' => '0',
        ]);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid(), $this->collectErrors($form));
    }

    public function testSubmitStillRejectsUnexpectedExtraField(): void
    {
        [$environment, $suite] = $this->createEnvironmentAndSuite();

        $form = $this->formFactory->create(TestRunType::class, null, [
            'csrf_protection' => false,
        ]);

        $form->submit([
            'suite' => (string) $suite->getId(),
            'environment' => (string) $environment->getId(),
            'unexpectedField' => 'value',
        ]);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('extra fields', $this->collectErrors($form));
    }

    /**
     * @return array{TestEnvironment, TestSuite}
     */
    private function createEnvironmentAndSuite(): array
    {
        $suffix = bin2hex(random_bytes(4));

        $environment = new TestEnvironment();
        $environment->setName("FormEnv_{$suffix}");
        $environment->setCode("form-env-{$suffix}");
        $environment->setRegion('us');
        $environment->setBaseUrl("https://form-{$suffix}.example.com");
        $environment->setBackendName('admin');
        $environment->setIsActive(true);

        $suite = new TestSuite();
        $suite->setName("FormSuite_{$suffix}");
        $suite->setType(TestSuite::TYPE_MFTF_GROUP);
        $suite->setTestPattern("FormPattern_{$suffix}");
        $suite->setIsActive(true);
        $suite->addEnvironment($environment);

        $this->entityManager->persist($environment);
        $this->entityManager->persist($suite);
        $this->entityManager->flush();

        return [$environment, $suite];
    }

    private function collectErrors(FormInterface $form): string
    {
        $messages = [];

        foreach ($form->getErrors(true, false) as $error) {
            $origin = $error->getOrigin();
            $name = $origin ? $origin->getName() : 'form';
            $messages[] = sprintf('%s: %s', $name, $error->getMessage());
        }

        return implode('; ', $messages);
    }
}
