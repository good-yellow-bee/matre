<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Handles Git operations for cloning test modules.
 */
class ModuleCloneService
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $moduleRepo,
        private readonly string $moduleBranch,
        private readonly string $projectDir,
        private readonly ?string $repoUsername = null,
        private readonly ?string $repoPassword = null,
        private readonly ?string $devModulePath = null,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Check if dev mode is enabled (local module path configured).
     */
    public function isDevModeEnabled(): bool
    {
        return !empty($this->devModulePath);
    }

    /**
     * Get resolved dev module path (absolute).
     */
    public function getDevModulePath(): ?string
    {
        if (empty($this->devModulePath)) {
            return null;
        }

        // Handle relative paths
        if (!str_starts_with($this->devModulePath, '/')) {
            return $this->projectDir . '/' . $this->devModulePath;
        }

        return $this->devModulePath;
    }

    /**
     * Use local module via symlink (dev mode).
     *
     * @throws \RuntimeException if dev module path doesn't exist
     */
    public function useLocalModule(string $targetPath): void
    {
        $sourcePath = $this->getDevModulePath();

        if (!$this->filesystem->exists($sourcePath)) {
            throw new \RuntimeException(sprintf('Dev module path does not exist: %s (DEV_MODULE_PATH=%s)', $sourcePath, $this->devModulePath));
        }

        $this->logger->info('Using local module (dev mode)', [
            'source' => $sourcePath,
            'target' => $targetPath,
        ]);

        // Remove existing target if any
        if ($this->filesystem->exists($targetPath)) {
            $this->filesystem->remove($targetPath);
        }

        // Create parent dir if needed
        $this->filesystem->mkdir(\dirname($targetPath));

        // Create symlink to local module
        $this->filesystem->symlink(
            (string) realpath($sourcePath),
            $targetPath,
        );

        $this->logger->info('Local module symlinked successfully');
    }

    /**
     * Clone module repository to target path (or use local module in dev mode).
     */
    public function cloneModule(string $targetPath): void
    {
        // Dev mode: use local module via symlink
        if ($this->isDevModeEnabled()) {
            $this->useLocalModule($targetPath);

            return;
        }

        $repoUrl = $this->getAuthenticatedRepoUrl();

        $this->logger->info('Cloning module', [
            'repo' => $this->moduleRepo,
            'branch' => $this->moduleBranch,
            'target' => $targetPath,
        ]);

        // Ensure target directory exists and is empty
        if ($this->filesystem->exists($targetPath)) {
            $this->filesystem->remove($targetPath);
        }
        $this->filesystem->mkdir($targetPath);

        // Clone repository
        $process = new Process([
            'git', 'clone',
            '--branch', $this->moduleBranch,
            '--depth', '1',
            '--single-branch',
            $repoUrl,
            $targetPath,
        ]);
        $process->setTimeout(300);

        try {
            $process->mustRun();
            $this->logger->info('Module cloned successfully');
        } catch (ProcessFailedException $e) {
            $this->logger->error('Failed to clone module', [
                'error' => $e->getMessage(),
                'output' => $this->sanitizeOutput($process->getErrorOutput()),
            ]);

            throw $e;
        }
    }

    /**
     * Pull latest changes from repository.
     */
    public function pullLatest(string $targetPath): void
    {
        if (!$this->filesystem->exists($targetPath . '/.git')) {
            throw new \RuntimeException('Target path is not a git repository');
        }

        $this->logger->info('Pulling latest changes', ['path' => $targetPath]);

        $process = new Process(['git', 'pull', 'origin', $this->moduleBranch], $targetPath);
        $process->setTimeout(120);

        try {
            $process->mustRun();
            $this->logger->info('Pull completed successfully');
        } catch (ProcessFailedException $e) {
            $this->logger->error('Failed to pull changes', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the default module target path.
     */
    public function getDefaultTargetPath(): string
    {
        return $this->projectDir . '/var/test-modules/current';
    }

    /**
     * Get path for a specific test run.
     */
    public function getRunTargetPath(int $runId): string
    {
        return $this->projectDir . '/var/test-modules/run-' . $runId;
    }

    /**
     * Clean up module directory.
     */
    public function cleanup(string $path): void
    {
        if ($this->filesystem->exists($path)) {
            $this->logger->info('Cleaning up module directory', ['path' => $path]);
            $this->filesystem->remove($path);
        }
    }

    /**
     * Get current commit hash.
     */
    public function getCommitHash(string $targetPath): ?string
    {
        if (!$this->filesystem->exists($targetPath . '/.git')) {
            return null;
        }

        $process = new Process(['git', 'rev-parse', 'HEAD'], $targetPath);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    public function getModuleRepo(): string
    {
        return $this->moduleRepo;
    }

    public function getModuleBranch(): string
    {
        return $this->moduleBranch;
    }

    /**
     * Get repository URL with embedded credentials for HTTPS repos.
     */
    private function getAuthenticatedRepoUrl(): string
    {
        // If not HTTPS or no credentials, return original URL
        if (!str_starts_with($this->moduleRepo, 'https://') || empty($this->repoUsername) || empty($this->repoPassword)) {
            return $this->moduleRepo;
        }

        // Inject credentials into HTTPS URL
        // https://repo.example.com/path.git -> https://user:pass@repo.example.com/path.git
        $parsed = parse_url($this->moduleRepo);
        if (!$parsed || !isset($parsed['host'])) {
            return $this->moduleRepo;
        }

        $credentials = urlencode($this->repoUsername) . ':' . urlencode($this->repoPassword);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';

        return sprintf('%s://%s@%s%s%s', $scheme, $credentials, $host, $port, $path);
    }

    /**
     * Sanitize output to remove credentials from error messages.
     */
    private function sanitizeOutput(string $output): string
    {
        if (!empty($this->repoUsername) && !empty($this->repoPassword)) {
            // Remove credentials from output
            $output = str_replace(
                urlencode($this->repoUsername) . ':' . urlencode($this->repoPassword) . '@',
                '***:***@',
                $output,
            );
            $output = str_replace($this->repoPassword, '***', $output);
        }

        return $output;
    }
}
