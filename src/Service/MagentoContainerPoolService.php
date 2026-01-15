<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestEnvironment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Process\Process;

/**
 * Manages per-environment Magento containers for parallel test execution.
 */
class MagentoContainerPoolService
{
    private const CONTAINER_PREFIX = 'matre_magento_env_';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
        private readonly string $projectDir,
        private readonly string $hostProjectDir,
        private readonly string $magentoImage,
        private readonly string $networkName,
        private readonly string $codeVolume,
    ) {
    }

    public function getContainerForEnvironment(TestEnvironment $env): string
    {
        $containerName = self::CONTAINER_PREFIX.$env->getId();

        $lock = $this->lockFactory->createLock('container_'.$containerName, 60);
        $lock->acquire(true);

        try {
            if (!$this->containerExists($containerName)) {
                $this->createContainer($containerName);
            } elseif (!$this->containerRunning($containerName)) {
                $this->startContainer($containerName);
            }
        } finally {
            $lock->release();
        }

        return $containerName;
    }

    private function containerExists(string $name): bool
    {
        $process = new Process([
            'docker', 'ps', '-a',
            '--filter', 'name=^/'.$name.'$',
            '--format', '{{.Names}}',
        ]);
        $process->run();

        return trim($process->getOutput()) === $name;
    }

    private function containerRunning(string $name): bool
    {
        $process = new Process([
            'docker', 'ps',
            '--filter', 'name=^/'.$name.'$',
            '--filter', 'status=running',
            '--format', '{{.Names}}',
        ]);
        $process->run();

        return trim($process->getOutput()) === $name;
    }

    private function startContainer(string $name): void
    {
        $this->logger->info('Starting existing container', ['container' => $name]);

        $process = new Process(['docker', 'start', $name]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Failed to start container %s: %s', $name, $process->getErrorOutput()));
        }
    }

    private function createContainer(string $name): void
    {
        $this->logger->info('Creating per-environment Magento container', ['container' => $name]);

        // Use host paths for docker bind mounts (not container /app path)
        $testModulePath = $this->hostProjectDir.'/var/test-modules/current';
        $mftfResultsPath = $this->hostProjectDir.'/var/mftf-results';
        $abbModulePath = $this->hostProjectDir.'/abb-custom-mftf';

        $process = new Process([
            'docker', 'run', '-d',
            '--name', $name,
            '--network', $this->networkName,
            // Shared code volume (writable - needed for nested mounts)
            '-v', $this->codeVolume.':/var/www/html',
            // Test module (read-only)
            '-v', $testModulePath.':/var/www/html/app/code/TestModule:ro',
            // MFTF results (writable - for screenshots, output)
            '-v', $mftfResultsPath.':/var/www/html/dev/tests/acceptance/tests/_output',
            // Allure results (writable)
            '-v', $mftfResultsPath.'/allure-results:/var/www/html/dev/tests/acceptance/allure-results',
            // Dev mode module mount
            '-v', $abbModulePath.':/app/abb-custom-mftf:ro',
            // Per-environment tmpfs for .env isolation (prevents shared volume race condition)
            '--mount', 'type=tmpfs,destination=/var/www/html/dev/tests/acceptance/env-config',
            // Environment
            '-e', 'MAGENTO_RUN_MODE=developer',
            $this->magentoImage,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Failed to create container %s: %s', $name, $process->getErrorOutput()));
        }

        // Wait for container to be ready
        $this->waitForContainer($name);
    }

    private function waitForContainer(string $name, int $timeoutSeconds = 30): void
    {
        $start = time();

        while (time() - $start < $timeoutSeconds) {
            if ($this->containerRunning($name)) {
                $this->logger->info('Container is ready', ['container' => $name]);

                return;
            }
            usleep(500000); // 500ms
        }

        throw new \RuntimeException(sprintf('Container %s did not start within %d seconds', $name, $timeoutSeconds));
    }

    public function cleanupEnvironmentContainer(TestEnvironment $env): void
    {
        $containerName = self::CONTAINER_PREFIX.$env->getId();

        if ($this->containerExists($containerName)) {
            $this->removeContainer($containerName);
        }
    }

    public function cleanupAllContainers(): int
    {
        $process = new Process([
            'docker', 'ps', '-a',
            '--filter', 'name='.self::CONTAINER_PREFIX,
            '--format', '{{.Names}}',
        ]);
        $process->run();

        $containers = array_filter(explode("\n", trim($process->getOutput())));
        $removed = 0;

        foreach ($containers as $containerName) {
            $this->removeContainer($containerName);
            ++$removed;
        }

        return $removed;
    }

    private function removeContainer(string $name): void
    {
        $this->logger->info('Removing container', ['container' => $name]);

        $process = new Process(['docker', 'rm', '-f', $name]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->warning('Failed to remove container', [
                'container' => $name,
                'error' => $process->getErrorOutput(),
            ]);
        }
    }
}
