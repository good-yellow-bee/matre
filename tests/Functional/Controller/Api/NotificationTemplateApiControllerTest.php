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

        $this->assertApiUnauthenticated($client);
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
    // Toggle Active / Reset Defaults CSRF
    // =====================

    public function testToggleActiveRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        $template = $this->createNotificationTemplate();

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/' . $template->getId() . '/toggle-active', [], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
    }

    public function testResetDefaultsRequiresCsrf(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);

        $response = $this->jsonRequest($client, 'POST', self::BASE_URL . '/reset-defaults', [], self::INVALID_CSRF_HEADERS);

        $this->assertJsonError($response, 403, 'CSRF');
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

        $template = $em->getRepository(NotificationTemplate::class)->findOneBy([
            'channel' => NotificationTemplate::CHANNEL_EMAIL,
            'name' => NotificationTemplate::NAME_COMPLETED_SUCCESS,
        ]);

        if (!$template) {
            $template = new NotificationTemplate();
            $template->setChannel(NotificationTemplate::CHANNEL_EMAIL);
            $template->setName(NotificationTemplate::NAME_COMPLETED_SUCCESS);
            $em->persist($template);
        }

        $template->setSubject('Test Subject');
        $template->setBody('Test body {{ testRunId }}');
        $template->setIsActive(true);
        $em->flush();

        return $template;
    }
}
