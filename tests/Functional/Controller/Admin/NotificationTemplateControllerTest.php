<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\NotificationTemplate;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for NotificationTemplateController.
 */
class NotificationTemplateControllerTest extends WebTestCase
{
    use ApiTestTrait;

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testIndexRequiresAuth(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/notification-templates');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);

        $client->request('GET', '/admin/notification-templates');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/admin/notification-templates');

        $this->assertResponseStatusCodeSame(200);
    }

    // =====================
    // Edit Tests
    // =====================

    public function testEditRequiresAuth(): void
    {
        $client = self::createClient();
        $template = $this->createNotificationTemplate();

        $client->request('GET', '/admin/notification-templates/' . $template->getId() . '/edit');

        $this->assertResponseRedirects('/login');
    }

    public function testEditReturns200(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $template = $this->createNotificationTemplate();

        $client->request('GET', '/admin/notification-templates/' . $template->getId() . '/edit');

        $this->assertResponseStatusCodeSame(200);
    }

    // =====================
    // Toggle Active Tests
    // =====================

    public function testToggleActiveRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $template = $this->createNotificationTemplate();

        $client->request('POST', '/admin/notification-templates/' . $template->getId() . '/toggle-active');

        $this->assertResponseRedirects('/admin/notification-templates');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    // =====================
    // Reset Defaults Tests
    // =====================

    public function testResetDefaultsRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $client->request('POST', '/admin/notification-templates/reset-defaults');

        $this->assertResponseRedirects('/admin/notification-templates');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Invalid CSRF token');
    }

    // =====================
    // Helpers
    // =====================

    private function createNotificationTemplate(): NotificationTemplate
    {
        $em = $this->getEntityManager();

        $existing = $em->getRepository(NotificationTemplate::class)->findOneBy([
            'channel' => NotificationTemplate::CHANNEL_EMAIL,
            'name' => NotificationTemplate::NAME_COMPLETED_SUCCESS,
        ]);

        if ($existing) {
            return $existing;
        }

        $template = new NotificationTemplate();
        $template->setChannel(NotificationTemplate::CHANNEL_EMAIL);
        $template->setName(NotificationTemplate::NAME_COMPLETED_SUCCESS);
        $template->setSubject('Test Subject');
        $template->setBody('Test body with {{ testRunId }}');
        $template->setIsActive(true);

        $em->persist($template);
        $em->flush();

        return $template;
    }
}
