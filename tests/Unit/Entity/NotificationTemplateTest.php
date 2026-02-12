<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\NotificationTemplate;
use PHPUnit\Framework\TestCase;

class NotificationTemplateTest extends TestCase
{
    public function testConstructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $template = new NotificationTemplate();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $template->getCreatedAt());
        $this->assertLessThanOrEqual($after, $template->getCreatedAt());
    }

    public function testIdIsNullByDefault(): void
    {
        $template = new NotificationTemplate();

        $this->assertNull($template->getId());
    }

    public function testChannelGetterAndSetter(): void
    {
        $template = new NotificationTemplate();

        $result = $template->setChannel(NotificationTemplate::CHANNEL_SLACK);

        $this->assertEquals('slack', $template->getChannel());
        $this->assertSame($template, $result);
    }

    public function testNameGetterAndSetter(): void
    {
        $template = new NotificationTemplate();

        $result = $template->setName(NotificationTemplate::NAME_COMPLETED_SUCCESS);

        $this->assertEquals('test_run_completed_success', $template->getName());
        $this->assertSame($template, $result);
    }

    public function testSubjectGetterAndSetter(): void
    {
        $template = new NotificationTemplate();

        $this->assertNull($template->getSubject());

        $result = $template->setSubject('Test Passed');

        $this->assertEquals('Test Passed', $template->getSubject());
        $this->assertSame($template, $result);
    }

    public function testBodyGetterAndSetter(): void
    {
        $template = new NotificationTemplate();

        $result = $template->setBody('Run {{ run_id }} completed');

        $this->assertEquals('Run {{ run_id }} completed', $template->getBody());
        $this->assertSame($template, $result);
    }

    public function testIsActiveDefaultAndSetter(): void
    {
        $template = new NotificationTemplate();

        $this->assertTrue($template->isActive());

        $result = $template->setIsActive(false);

        $this->assertFalse($template->isActive());
        $this->assertSame($template, $result);
    }

    public function testIsDefaultDefaultAndSetter(): void
    {
        $template = new NotificationTemplate();

        $this->assertFalse($template->isDefault());

        $result = $template->setIsDefault(true);

        $this->assertTrue($template->isDefault());
        $this->assertSame($template, $result);
    }

    public function testGetNameLabelWithKnownName(): void
    {
        $template = new NotificationTemplate();
        $template->setName(NotificationTemplate::NAME_FAILED);

        $this->assertEquals('Test Run Failed', $template->getNameLabel());
    }

    public function testGetNameLabelFallsBackToName(): void
    {
        $template = new NotificationTemplate();
        $template->setName('custom_event');

        $this->assertEquals('custom_event', $template->getNameLabel());
    }

    public function testToString(): void
    {
        $template = new NotificationTemplate();
        $template->setChannel(NotificationTemplate::CHANNEL_EMAIL);
        $template->setName(NotificationTemplate::NAME_CANCELLED);

        $this->assertEquals('Email - Test Run Cancelled', (string) $template);
    }

    public function testToStringWithUnknownName(): void
    {
        $template = new NotificationTemplate();
        $template->setChannel(NotificationTemplate::CHANNEL_SLACK);
        $template->setName('custom_event');

        $this->assertEquals('Slack - custom_event', (string) $template);
    }

    public function testSetUpdatedAtSetsCurrentTime(): void
    {
        $template = new NotificationTemplate();

        $this->assertNull($template->getUpdatedAt());

        $before = new \DateTimeImmutable();
        $template->setUpdatedAt();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $template->getUpdatedAt());
        $this->assertLessThanOrEqual($after, $template->getUpdatedAt());
    }

    public function testSetCreatedAtSetter(): void
    {
        $template = new NotificationTemplate();
        $date = new \DateTimeImmutable('2024-06-01');

        $result = $template->setCreatedAt($date);

        $this->assertSame($date, $template->getCreatedAt());
        $this->assertSame($template, $result);
    }

    public function testChannelConstants(): void
    {
        $this->assertEquals('slack', NotificationTemplate::CHANNEL_SLACK);
        $this->assertEquals('email', NotificationTemplate::CHANNEL_EMAIL);
        $this->assertContains(NotificationTemplate::CHANNEL_SLACK, NotificationTemplate::CHANNELS);
        $this->assertContains(NotificationTemplate::CHANNEL_EMAIL, NotificationTemplate::CHANNELS);
    }

    public function testNameConstants(): void
    {
        $this->assertContains(NotificationTemplate::NAME_COMPLETED_SUCCESS, NotificationTemplate::NAMES);
        $this->assertContains(NotificationTemplate::NAME_COMPLETED_FAILURES, NotificationTemplate::NAMES);
        $this->assertContains(NotificationTemplate::NAME_FAILED, NotificationTemplate::NAMES);
        $this->assertContains(NotificationTemplate::NAME_CANCELLED, NotificationTemplate::NAMES);
        $this->assertCount(4, NotificationTemplate::NAMES);
        $this->assertCount(4, NotificationTemplate::NAME_LABELS);
    }

    public function testFluentInterface(): void
    {
        $template = new NotificationTemplate();

        $result = $template
            ->setChannel(NotificationTemplate::CHANNEL_SLACK)
            ->setName(NotificationTemplate::NAME_FAILED)
            ->setSubject('Alert')
            ->setBody('Tests failed')
            ->setIsActive(true)
            ->setIsDefault(false)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->assertSame($template, $result);
    }
}
