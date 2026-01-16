<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Discovers MFTF tests and groups from cached test module.
 *
 * Manages a persistent cache of the test module repository for discovering
 * available tests and groups to use in test suite configuration.
 */
class TestDiscoveryService
{
    private const CACHE_DIR = 'var/test-module-cache';

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly string $moduleRepo,
        private readonly string $moduleBranch,
        private readonly ?string $repoUsername = null,
        private readonly ?string $repoPassword = null,
        private readonly ?string $devModulePath = null,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Check if cache is available (cloned or dev mode).
     */
    public function isCacheAvailable(): bool
    {
        return null !== $this->getCachePath();
    }

    /**
     * Get the cache path if available.
     */
    public function getCachePath(): ?string
    {
        // Dev mode takes priority
        if (!empty($this->devModulePath)) {
            $path = $this->resolveDevPath();
            if (null !== $path && is_dir($path)) {
                return $path;
            }
        }

        // Check persistent cache
        $cachePath = $this->projectDir . '/' . self::CACHE_DIR;
        if (is_dir($cachePath . '/.git')) {
            return $cachePath;
        }

        return null;
    }

    /**
     * Ensure cache exists, clone if needed.
     *
     * @return string Path to the cached module
     *
     * @throws \RuntimeException if clone fails
     */
    public function ensureCache(): string
    {
        // Dev mode: use local module
        if (!empty($this->devModulePath)) {
            $path = $this->resolveDevPath();
            if (null !== $path && is_dir($path)) {
                return $path;
            }

            throw new \RuntimeException(sprintf('Dev module path does not exist: %s', $this->devModulePath));
        }

        $cachePath = $this->projectDir . '/' . self::CACHE_DIR;

        // Already cloned
        if (is_dir($cachePath . '/.git')) {
            return $cachePath;
        }

        // Clone fresh
        $this->cloneRepository($cachePath);

        return $cachePath;
    }

    /**
     * Refresh cache from remote (git fetch + reset).
     *
     * @throws \RuntimeException if refresh fails
     */
    public function refreshCache(): void
    {
        // Dev mode: nothing to refresh
        if (!empty($this->devModulePath)) {
            $this->logger->info('Dev mode active, skipping refresh');

            return;
        }

        $cachePath = $this->projectDir . '/' . self::CACHE_DIR;

        if (!is_dir($cachePath . '/.git')) {
            // Not cloned yet, do initial clone
            $this->cloneRepository($cachePath);

            return;
        }

        $this->logger->info('Refreshing test module cache', [
            'path' => $cachePath,
            'branch' => $this->moduleBranch,
        ]);

        // Fetch latest
        $process = new Process(['git', 'fetch', 'origin'], $cachePath);
        $process->setTimeout(120);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            $this->logger->error('Failed to fetch', ['error' => $e->getMessage()]);

            throw $e;
        }

        // Reset to remote branch
        $process = new Process(
            ['git', 'reset', '--hard', 'origin/' . $this->moduleBranch],
            $cachePath,
        );
        $process->setTimeout(60);

        try {
            $process->mustRun();
            $this->logger->info('Cache refreshed successfully');
        } catch (ProcessFailedException $e) {
            $this->logger->error('Failed to reset', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Get all MFTF test names from cached module.
     *
     * @return array<string> List of test names (e.g., ['MOEC1625Test', 'CheckoutTest'])
     */
    public function getMftfTests(): array
    {
        $cachePath = $this->getCachePath();
        if (null === $cachePath) {
            return [];
        }

        $testDir = $this->findTestDirectory($cachePath);
        if (null === $testDir) {
            return [];
        }

        $tests = [];
        $finder = new Finder();
        $finder->files()->in($testDir)->name('*.xml');

        foreach ($finder as $file) {
            $content = $file->getContents();
            // Extract test name from <test name="...">
            if (preg_match('/<test\s+name="([^"]+)"/', $content, $matches)) {
                $tests[] = $matches[1];
            }
        }

        sort($tests);

        return array_values(array_unique($tests));
    }

    /**
     * Get all MFTF group names from cached module.
     *
     * @return array<string> List of unique group names (e.g., ['checkout', 'pricing'])
     */
    public function getMftfGroups(): array
    {
        $cachePath = $this->getCachePath();
        if (null === $cachePath) {
            return [];
        }

        $testDir = $this->findTestDirectory($cachePath);
        if (null === $testDir) {
            return [];
        }

        $groups = [];
        $finder = new Finder();
        $finder->files()->in($testDir)->name('*.xml');

        foreach ($finder as $file) {
            $content = $file->getContents();
            // Extract group values from <group value="..."/>
            preg_match_all('/<group\s+value="([^"]+)"/', $content, $matches);
            foreach ($matches[1] as $group) {
                $groups[$group] = true;
            }
        }

        $result = array_keys($groups);
        sort($result);

        return $result;
    }

    /**
     * Resolve MFTF group name to list of test names.
     *
     * @param string      $groupName  The group name to resolve
     * @param string|null $modulePath Path to module directory (uses cache if null)
     *
     * @return array<string> Test names belonging to this group
     */
    public function resolveGroupToTests(string $groupName, ?string $modulePath = null): array
    {
        // CRITICAL: Use provided module path (cloned for this run) not cache
        $basePath = $modulePath ?? $this->getCachePath();
        if (null === $basePath) {
            $this->logger->warning('No module path available for group resolution', [
                'groupName' => $groupName,
            ]);

            return [];
        }

        $testDir = $this->findTestDirectory($basePath);
        if (null === $testDir) {
            $this->logger->warning('Test directory not found', [
                'basePath' => $basePath,
                'groupName' => $groupName,
            ]);

            return [];
        }

        $tests = [];
        $finder = new Finder();
        $finder->files()->in($testDir)->name('*.xml');

        foreach ($finder as $file) {
            $content = $file->getContents();
            // Check if file has this group annotation
            $pattern = '/<group\s+value="' . preg_quote($groupName, '/') . '"/';
            if (preg_match($pattern, $content)) {
                // Extract test name from same file
                if (preg_match('/<test\s+name="([^"]+)"/', $content, $matches)) {
                    $tests[] = $matches[1];
                }
            }
        }

        $this->logger->info('Resolved group to tests', [
            'groupName' => $groupName,
            'testCount' => count($tests),
            'tests' => $tests,
        ]);

        sort($tests);

        return array_values(array_unique($tests));
    }

    /**
     * Get last cache update time.
     */
    public function getLastUpdated(): ?\DateTimeInterface
    {
        $cachePath = $this->getCachePath();
        if (null === $cachePath) {
            return null;
        }

        // For dev mode, use current time (always fresh)
        if (!empty($this->devModulePath)) {
            return new \DateTimeImmutable();
        }

        // Get last commit time
        $process = new Process(
            ['git', 'log', '-1', '--format=%ci'],
            $cachePath,
        );
        $process->run();

        if ($process->isSuccessful()) {
            $output = trim($process->getOutput());
            if (!empty($output)) {
                return new \DateTimeImmutable($output);
            }
        }

        return null;
    }

    /**
     * Clone repository to target path.
     */
    private function cloneRepository(string $targetPath): void
    {
        $repoUrl = $this->getAuthenticatedRepoUrl();

        $this->logger->info('Cloning test module for discovery', [
            'repo' => $this->moduleRepo,
            'branch' => $this->moduleBranch,
            'target' => $targetPath,
        ]);

        // Ensure clean target
        if ($this->filesystem->exists($targetPath)) {
            $this->filesystem->remove($targetPath);
        }
        $this->filesystem->mkdir($targetPath);

        $process = new Process([
            'git', 'clone',
            '--branch', $this->moduleBranch,
            '--single-branch',
            $repoUrl,
            $targetPath,
        ]);
        $process->setTimeout(300);

        try {
            $process->mustRun();
            $this->logger->info('Test module cloned for discovery');
        } catch (ProcessFailedException $e) {
            $this->logger->error('Failed to clone module', [
                'error' => $e->getMessage(),
                'output' => $this->sanitizeOutput($process->getErrorOutput()),
            ]);

            throw $e;
        }
    }

    /**
     * Find the MFTF Test directory within the module.
     */
    private function findTestDirectory(string $modulePath): ?string
    {
        $patterns = [
            '/Test/Mftf/Test',
            '/Mftf/Test',
            '/Test/Test',
        ];

        foreach ($patterns as $pattern) {
            $testDir = rtrim($modulePath, '/') . $pattern;
            if (is_dir($testDir)) {
                return $testDir;
            }
        }

        return null;
    }

    /**
     * Resolve dev module path to absolute.
     */
    private function resolveDevPath(): ?string
    {
        if (empty($this->devModulePath)) {
            return null;
        }

        if (str_starts_with($this->devModulePath, '/')) {
            return $this->devModulePath;
        }

        return $this->projectDir . '/' . $this->devModulePath;
    }

    /**
     * Get repository URL with embedded credentials.
     */
    private function getAuthenticatedRepoUrl(): string
    {
        if (!str_starts_with($this->moduleRepo, 'https://')
            || empty($this->repoUsername)
            || empty($this->repoPassword)) {
            return $this->moduleRepo;
        }

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
     * Sanitize output to remove credentials.
     */
    private function sanitizeOutput(string $output): string
    {
        if (!empty($this->repoUsername) && !empty($this->repoPassword)) {
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
