<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Settings;
use App\EventSubscriber\HeadlessModeSubscriber;
use App\Repository\SettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class HeadlessModeSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsReturnsKernelRequest(): void
    {
        $events = HeadlessModeSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testSkipsSubRequest(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'cms_homepage');
        $event = $this->createEvent($request, HttpKernelInterface::SUB_REQUEST);

        $this->createSubscriber($this->createSettingsRepository(true))->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsNonCmsRoutes(): void
    {
        $request = Request::create('/admin');
        $request->attributes->set('_route', 'admin_dashboard');
        $event = $this->createEvent($request);

        $this->createSubscriber($this->createSettingsRepository(true))->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testBlocksCmsRouteWhenHeadlessEnabled(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'cms_homepage');
        $event = $this->createEvent($request);

        $this->createSubscriber($this->createSettingsRepository(true))->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(404, $event->getResponse()->getStatusCode());
    }

    public function testAllowsCmsRouteWhenHeadlessDisabled(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'cms_homepage');
        $event = $this->createEvent($request);

        $this->createSubscriber($this->createSettingsRepository(false))->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    private function createSubscriber(?SettingsRepository $settingsRepository = null): HeadlessModeSubscriber
    {
        return new HeadlessModeSubscriber(
            $settingsRepository ?? $this->createStub(SettingsRepository::class),
        );
    }

    private function createEvent(
        Request $request,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): RequestEvent {
        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            $requestType,
        );
    }

    private function createSettingsRepository(bool $headlessMode): SettingsRepository
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('isHeadlessMode')->willReturn($headlessMode);

        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('getSettings')->willReturn($settings);

        return $repository;
    }
}
