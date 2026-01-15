<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:validate-ssl-config',
    description: 'Validate SSL/TLS configuration for production deployment',
)]
class ValidateSslConfigCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('SSL Configuration Validation');

        $checks = [];
        $hasErrors = false;

        // Check APP_DOMAIN
        $domain = $_ENV['APP_DOMAIN'] ?? '';
        if (empty($domain)) {
            $checks[] = ['APP_DOMAIN', 'Not set', 'error'];
            $hasErrors = true;
        } elseif (filter_var($domain, FILTER_VALIDATE_IP)) {
            $checks[] = ['APP_DOMAIN', $domain, 'warning'];
            $io->warning('APP_DOMAIN is an IP address. Let\'s Encrypt requires a domain name.');
        } elseif (str_ends_with($domain, '.local')) {
            $checks[] = ['APP_DOMAIN', $domain, 'info'];
            $io->note('Local domain detected. Let\'s Encrypt won\'t work with .local domains.');
        } else {
            $checks[] = ['APP_DOMAIN', $domain, 'success'];
        }

        // Check CERT_RESOLVER
        $certResolver = $_ENV['CERT_RESOLVER'] ?? '';
        $validResolvers = ['', 'letsencrypt'];

        if (!in_array($certResolver, $validResolvers, true)) {
            $checks[] = ['CERT_RESOLVER', $certResolver ?: '(empty)', 'error'];
            $io->error(sprintf('Invalid CERT_RESOLVER. Valid values: %s', implode(', ', $validResolvers)));
            $hasErrors = true;
        } else {
            $checks[] = ['CERT_RESOLVER', $certResolver ?: '(empty - no auto cert)', 'success'];
        }

        // Check LETSENCRYPT_EMAIL when resolver is set
        $email = $_ENV['LETSENCRYPT_EMAIL'] ?? '';
        if ('letsencrypt' === $certResolver) {
            if (empty($email)) {
                $checks[] = ['LETSENCRYPT_EMAIL', 'Not set', 'error'];
                $io->error('LETSENCRYPT_EMAIL is required when CERT_RESOLVER=letsencrypt');
                $hasErrors = true;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $checks[] = ['LETSENCRYPT_EMAIL', $email, 'error'];
                $io->error('LETSENCRYPT_EMAIL is not a valid email address');
                $hasErrors = true;
            } else {
                $checks[] = ['LETSENCRYPT_EMAIL', $email, 'success'];
            }
        } else {
            $checks[] = ['LETSENCRYPT_EMAIL', $email ?: '(not required)', 'info'];
        }

        // Check for production readiness
        $appEnv = $_ENV['APP_ENV'] ?? 'dev';
        if ('letsencrypt' === $certResolver && 'prod' !== $appEnv) {
            $io->warning(sprintf(
                'APP_ENV=%s with Let\'s Encrypt. Consider APP_ENV=prod for production.',
                $appEnv,
            ));
        }

        // Summary table
        $io->section('Configuration Summary');

        $rows = [];
        foreach ($checks as [$name, $value, $level]) {
            $icon = match ($level) {
                'success' => "\u{2705}",
                'warning' => "\u{26A0}\u{FE0F}",
                'error' => "\u{274C}",
                'info' => "\u{2139}\u{FE0F}",
                default => "\u{2022}",
            };
            $rows[] = [$icon, $name, $value];
        }

        $io->table(['', 'Variable', 'Value'], $rows);

        // Production checklist
        if ('letsencrypt' === $certResolver && !$hasErrors) {
            $io->section('Production Deployment Checklist');
            $io->listing([
                'DNS A record points to this server',
                'Ports 80 and 443 are open/forwarded',
                'No other service using ports 80/443',
                'Domain is publicly accessible',
            ]);
        }

        if ($hasErrors) {
            $io->error('Configuration has errors. Fix issues above before deploying.');

            return Command::FAILURE;
        }

        $io->success('SSL configuration is valid.');

        return Command::SUCCESS;
    }
}
