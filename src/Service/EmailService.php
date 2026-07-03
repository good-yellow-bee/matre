<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Service for sending emails with template support.
 *
 * Provides a convenient wrapper around Symfony Mailer for sending
 * templated emails with common patterns (welcome emails, password resets, etc.).
 */
class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail = 'noreply@example.com',
        private readonly string $fromName = 'ReSymf CMS',
    ) {
    }

    /**
     * Send a welcome email to a new user.
     *
     * @param string $toEmail The recipient's email address
     * @param string $userName The recipient's name
     *
     * @throws TransportExceptionInterface If sending fails
     */
    public function sendWelcomeEmail(string $toEmail, string $userName): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject('Welcome to ' . $this->fromName)
            ->htmlTemplate('emails/welcome.html.twig')
            ->context([
                'userName' => $userName,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send a password reset email.
     *
     * @param string $toEmail The recipient's email address
     * @param string $userName The recipient's name
     * @param string $resetToken The password reset token
     * @param string $resetUrl The password reset URL
     *
     * @throws TransportExceptionInterface If sending fails
     */
    public function sendPasswordResetEmail(
        string $toEmail,
        string $userName,
        string $resetToken,
        string $resetUrl,
    ): void {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject('Password Reset Request')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'userName' => $userName,
                'resetToken' => $resetToken,
                'resetUrl' => $resetUrl,
                'expirationTime' => '1 hour',
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send a password changed confirmation email.
     *
     * @param string $toEmail The recipient's email address
     * @param string $userName The recipient's name
     *
     * @throws TransportExceptionInterface If sending fails
     */
    public function sendPasswordChangedEmail(string $toEmail, string $userName): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject('Password Changed Successfully')
            ->htmlTemplate('emails/password_changed.html.twig')
            ->context([
                'userName' => $userName,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Send a generic notification email.
     *
     * @param string $toEmail The recipient's email address
     * @param string $subject The email subject
     * @param string $templatePath The Twig template path
     * @param array<string, mixed> $context Template context variables
     *
     * @throws TransportExceptionInterface If sending fails
     */
    public function sendNotification(
        string $toEmail,
        string $subject,
        string $templatePath,
        array $context = [],
    ): void {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject($subject)
            ->htmlTemplate($templatePath)
            ->context($context);

        $this->mailer->send($email);
    }
}
