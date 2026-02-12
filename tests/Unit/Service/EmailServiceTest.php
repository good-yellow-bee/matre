<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class EmailServiceTest extends TestCase
{
    public function testSendWelcomeEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return 'user@example.com' === $email->getTo()[0]->getAddress()
                    && 'Welcome to Test App' === $email->getSubject()
                    && 'emails/welcome.html.twig' === $email->getHtmlTemplate();
            }));

        $this->createService($mailer)->sendWelcomeEmail('user@example.com', 'John Doe');
    }

    public function testSendPasswordResetEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return 'user@example.com' === $email->getTo()[0]->getAddress()
                    && 'Password Reset Request' === $email->getSubject()
                    && 'emails/password_reset.html.twig' === $email->getHtmlTemplate();
            }));

        $this->createService($mailer)->sendPasswordResetEmail(
            'user@example.com',
            'John Doe',
            'reset-token-123',
            'https://example.com/reset/token123',
        );
    }

    public function testSendPasswordChangedEmail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return 'user@example.com' === $email->getTo()[0]->getAddress()
                    && 'Password Changed Successfully' === $email->getSubject()
                    && 'emails/password_changed.html.twig' === $email->getHtmlTemplate();
            }));

        $this->createService($mailer)->sendPasswordChangedEmail('user@example.com', 'John Doe');
    }

    public function testSendNotification(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return 'admin@example.com' === $email->getTo()[0]->getAddress()
                    && 'Test Notification' === $email->getSubject()
                    && 'emails/notification.html.twig' === $email->getHtmlTemplate();
            }));

        $this->createService($mailer)->sendNotification(
            'admin@example.com',
            'Test Notification',
            'emails/notification.html.twig',
            ['data' => 'test'],
        );
    }

    public function testSendContactFormNotification(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) {
                return 'admin@example.com' === $email->getTo()[0]->getAddress()
                    && str_contains($email->getSubject(), 'John Sender')
                    && 'emails/contact_form.html.twig' === $email->getHtmlTemplate()
                    && 'sender@example.com' === $email->getReplyTo()[0]->getAddress();
            }));

        $this->createService($mailer)->sendContactFormNotification(
            'admin@example.com',
            'John Sender',
            'sender@example.com',
            'Test message',
        );
    }

    public function testEmailServicePropagatesTransportException(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer
            ->method('send')
            ->willThrowException(new TransportException('SMTP error'));

        $this->expectException(TransportException::class);

        $this->createService($mailer)->sendWelcomeEmail('user@example.com', 'John Doe');
    }

    private function createService(?MailerInterface $mailer = null): EmailService
    {
        return new EmailService(
            $mailer ?? $this->createStub(MailerInterface::class),
            'test@example.com',
            'Test App',
        );
    }
}
