<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Api;

use App\Entity\NotificationTemplate;
use App\Tests\Functional\Traits\ApiTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for NotificationTemplateApiController.
 */
class NotificationTemplateApiControllerTest extends WebTestCase
{
    use ApiTestTrait;

    private const BASE_URL = '/api/notification-templates';

    protected function tearDown(): void
    {
        $this->entityManager = null;
        parent::tearDown();
    }

    // =====================
    // Authentication Tests
    // =====================

    public function testShowRequiresAuth(): void
    {
        $client = self::createClient();
        $template = $this->createNotificationTemplate();

        $client->request('GET', self::BASE_URL . '/' . $template->getId());

        $this->assertResponseRedirects('/login');
    }

    public function testShowRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $template = $this->createNotificationTemplate();

        $client->request('GET', self::BASE_URL . '/' . $template->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    // =====================
    // Show Template
    // =====================

    public function testShowReturnsTemplate(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $template = $this->createNotificationTemplate();

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/' . $template->getId());
        $data = $this->assertJsonResponse($response, 200);

        $this->assertEquals($template->getId(), $data['id']);
        $this->assertEquals(NotificationTemplate::CHANNEL_EMAIL, $data['channel']);
        $this->assertEquals(NotificationTemplate::NAME_COMPLETED_SUCCESS, $data['name']);
        $this->assertEquals('Test Subject', $data['subject']);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('isActive', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    // =====================
    // Update Template
    // =====================

    public function testUpdateRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $template = $this->createNotificationTemplate();

        $response = $this->jsonRequest($client, 'PUT', self::BASE_URL . '/' . $template->getId(), [
            'subject' => 'Updated Subject',
            'body' => 'Updated body',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    // =====================
    // Variables Endpoint
    // =====================

    public function testVariablesReturnsData(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'GET', self::BASE_URL . '/variables');
        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
    }

    // =====================
    // Preview Endpoint
    // =====================

    public function testPreviewRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $template = $this->createNotificationTemplate();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $template->getId() . '/preview', [
            'body' => 'Preview body',
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    // =====================
    // Reset Endpoint
    // =====================

    public function testResetRequiresAdmin(): void
    {
        $client = self::createClient();
        $this->loginAsUser($client);
        $template = $this->createNotificationTemplate();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $template->getId() . '/reset');

        $this->assertEquals(403, $response->getStatusCode());
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
        $template->setBody('Test body {{ testRunId }}');
        $template->setIsActive(true);
        $em->persist($template);
        $em->flush();

        return $template;
    }
}
