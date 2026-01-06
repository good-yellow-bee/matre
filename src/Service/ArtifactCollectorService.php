<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TestResult;
use App\Entity\TestRun;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Lock\LockFactory;

/**
 * Collects test artifacts (screenshots, HTML) from MFTF output and organizes them by run.
 */
class ArtifactCollectorService
{
    private const SCREENSHOT_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif'];
    private const HTML_EXTENSIONS = ['html', 'htm'];
    private const MAX_ARTIFACT_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory,
        private readonly string $projectDir,
        private readonly string $artifactsDir = 'var/test-artifacts',
        private readonly string $mftfResultsDir = 'var/mftf-results',
    ) {
    }

    /**
     * Collect artifacts from MFTF output directory for a test run.
     * Uses global lock to prevent artifact contamination between concurrent runs.
     *
     * @return array{screenshots: string[], html: string[]}
     */
    public function collectArtifacts(TestRun $run): array
    {
        $collected = ['screenshots' => [], 'html' => []];

        // Acquire global lock to prevent concurrent runs from mixing artifacts
        $lock = $this->lockFactory->createLock('artifact_collection', 300);
        $lock->acquire(true);

        try {
            $sourcePath = $this->projectDir . '/' . $this->mftfResultsDir . '/run-' . $run->getId();
            $targetPath = $this->getRunArtifactsPath($run);

            if (!is_dir($sourcePath)) {
                $this->logger->warning('MFTF results directory not found', ['path' => $sourcePath]);

                return $collected;
            }

            $filesystem = new Filesystem();
            $filesystem->mkdir($targetPath);

            // Collect screenshots
            $collected['screenshots'] = $this->collectFilesByExtension(
                $sourcePath,
                $targetPath,
                self::SCREENSHOT_EXTENSIONS,
                $filesystem,
            );

            // Collect HTML files
            $collected['html'] = $this->collectFilesByExtension(
                $sourcePath,
                $targetPath,
                self::HTML_EXTENSIONS,
                $filesystem,
            );

            $this->logger->info('Artifacts collected', [
                'run_id' => $run->getId(),
                'screenshots' => count($collected['screenshots']),
                'html' => count($collected['html']),
            ]);

            return $collected;
        } finally {
            $lock->release();
        }
    }

    /**
     * Associate collected screenshots with test results based on filename matching.
     *
     * @param TestResult[] $results
     * @param string[] $screenshotPaths
     */
    public function associateScreenshotsWithResults(array $results, array $screenshotPaths): void
    {
        foreach ($results as $result) {
            $testName = $result->getTestName();
            $testId = $result->getTestId();

            foreach ($screenshotPaths as $path) {
                $filename = basename($path);

                // Match by test name or test ID in filename
                if (
                    ($testName && stripos($filename, $testName) !== false)
                    || ($testId && stripos($filename, $testId) !== false)
                ) {
                    $result->setScreenshotPath($filename);
                    $this->logger->debug('Screenshot associated', [
                        'test' => $testName,
                        'screenshot' => $filename,
                    ]);

                    break;
                }
            }
        }
    }

    /**
     * Collect and associate screenshot and HTML for a specific test (incremental during execution).
     */
    public function collectTestScreenshot(TestRun $run, TestResult $result): void
    {
        $sourcePath = $this->projectDir . '/' . $this->mftfResultsDir . '/run-' . $run->getId();
        $targetPath = $this->getRunArtifactsPath($run);

        if (!is_dir($sourcePath)) {
            return;
        }

        $filesystem = new Filesystem();
        $filesystem->mkdir($targetPath);

        $testId = $result->getTestId();
        $testName = $result->getTestName();

        if (!$testId && !$testName) {
            $this->logger->debug('Cannot collect artifacts: TestResult missing identifiers', [
                'resultId' => $result->getId(),
            ]);

            return;
        }

        // Collect all artifact types (screenshots + HTML)
        $allExtensions = array_merge(self::SCREENSHOT_EXTENSIONS, self::HTML_EXTENSIONS);
        $screenshotCollected = false;

        $finder = new Finder();
        $finder->files()->in($sourcePath)->depth(0);

        // Build pattern for all extensions
        $patterns = array_map(fn ($ext) => '*.' . $ext, $allExtensions);
        $finder->name($patterns);

        foreach ($finder as $file) {
            $filename = $file->getFilename();

            // Match by test ID or test name
            if (
                ($testId && stripos($filename, $testId) !== false)
                || ($testName && stripos($filename, $testName) !== false)
            ) {
                if ($file->getSize() > self::MAX_ARTIFACT_SIZE) {
                    continue;
                }

                $targetFile = $targetPath . '/' . $filename;
                $filesystem->copy($file->getRealPath(), $targetFile, true);

                // Set screenshot path for first matching screenshot
                $ext = strtolower($file->getExtension());
                if (!$screenshotCollected && in_array($ext, self::SCREENSHOT_EXTENSIONS, true)) {
                    $result->setScreenshotPath($filename);
                    $screenshotCollected = true;
                }

                $this->logger->debug('Artifact collected for test', [
                    'test' => $testName,
                    'artifact' => $filename,
                ]);
            }
        }
    }

    /**
     * Get the artifacts directory path for a specific run.
     */
    public function getRunArtifactsPath(TestRun $run): string
    {
        return sprintf('%s/%s/%d', $this->projectDir, $this->artifactsDir, $run->getId());
    }

    /**
     * Clear old per-run directories to prevent disk space buildup.
     * Only removes run-* directories older than the specified number of days.
     *
     * @param int $keepDays Number of days to keep run directories (default: 7)
     */
    public function clearOldRunDirectories(int $keepDays = 7): void
    {
        $filesystem = new Filesystem();
        $baseDir = $this->projectDir . '/' . $this->mftfResultsDir;

        if (!$filesystem->exists($baseDir) || !is_dir($baseDir)) {
            return;
        }

        $finder = new Finder();
        $finder->directories()->in($baseDir)->depth(0)->name('run-*');

        $cutoff = new \DateTimeImmutable("-{$keepDays} days");
        $removed = 0;

        foreach ($finder as $dir) {
            if ($dir->getMTime() < $cutoff->getTimestamp()) {
                $this->logger->info('Removing old run directory', [
                    'path' => $dir->getPathname(),
                    'mtime' => date('Y-m-d H:i:s', $dir->getMTime()),
                ]);
                $filesystem->remove($dir->getPathname());
                ++$removed;
            }
        }

        if ($removed > 0) {
            $this->logger->info('Cleaned up old run directories', ['count' => $removed]);
        }
    }

    /**
     * Get web-accessible path for an artifact.
     */
    public function getArtifactWebPath(TestRun $run, string $filename): string
    {
        return sprintf('/admin/test-runs/%d/artifacts/%s', $run->getId(), $filename);
    }

    /**
     * Get full filesystem path to an artifact file.
     *
     * SECURITY: Validates that the requested file is within the run's artifact directory
     * to prevent path traversal attacks.
     *
     * @throws \InvalidArgumentException if filename contains path traversal attempts
     */
    public function getArtifactFilePath(TestRun $run, string $filename): string
    {
        // SECURITY: Sanitize filename to prevent path traversal
        $sanitizedFilename = $this->sanitizeFilename($filename);

        $basePath = $this->getRunArtifactsPath($run);
        $fullPath = $basePath . '/' . $sanitizedFilename;

        // SECURITY: Resolve real path and verify it's within the allowed directory
        // If file exists, use realpath; otherwise verify parent directory
        if (file_exists($fullPath)) {
            $realPath = realpath($fullPath);
            $realBase = realpath($basePath);

            if ($realPath === false || $realBase === false) {
                throw new \InvalidArgumentException('Invalid artifact path');
            }

            // Ensure the resolved path is within the artifacts directory
            if (!str_starts_with($realPath, $realBase . '/')) {
                throw new \InvalidArgumentException('Path traversal attempt detected');
            }

            return $realPath;
        }

        return $fullPath;
    }

    /**
     * Check if artifact file exists.
     *
     * SECURITY: Uses sanitized path to prevent path traversal attacks.
     */
    public function artifactExists(TestRun $run, string $filename): bool
    {
        try {
            $path = $this->getArtifactFilePath($run, $filename);

            return file_exists($path) && is_file($path);
        } catch (\InvalidArgumentException) {
            // Path traversal attempt or invalid path
            return false;
        }
    }

    /**
     * List all artifacts for a run.
     *
     * @return array{screenshots: string[], html: string[], other: string[]}
     */
    public function listArtifacts(TestRun $run): array
    {
        $path = $this->getRunArtifactsPath($run);
        $artifacts = ['screenshots' => [], 'html' => [], 'other' => []];

        if (!is_dir($path)) {
            return $artifacts;
        }

        $finder = new Finder();
        $finder->files()->in($path)->depth(0);

        foreach ($finder as $file) {
            $ext = strtolower($file->getExtension());
            $filename = $file->getFilename();

            if (in_array($ext, self::SCREENSHOT_EXTENSIONS, true)) {
                $artifacts['screenshots'][] = $filename;
            } elseif (in_array($ext, self::HTML_EXTENSIONS, true)) {
                $artifacts['html'][] = $filename;
            } else {
                $artifacts['other'][] = $filename;
            }
        }

        return $artifacts;
    }

    /**
     * Clean up artifacts older than specified days.
     */
    public function cleanupOldArtifacts(int $daysOld = 30): int
    {
        $baseDir = $this->projectDir . '/' . $this->artifactsDir;

        if (!is_dir($baseDir)) {
            return 0;
        }

        $filesystem = new Filesystem();
        $cutoff = new \DateTimeImmutable("-{$daysOld} days");
        $removed = 0;

        $finder = new Finder();
        $finder->directories()->in($baseDir)->depth(0);

        foreach ($finder as $dir) {
            if ($dir->getMTime() < $cutoff->getTimestamp()) {
                $filesystem->remove($dir->getRealPath());
                ++$removed;
            }
        }

        return $removed;
    }

    /**
     * Sanitize a filename to prevent path traversal attacks.
     *
     * @throws \InvalidArgumentException if filename is invalid
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Reject path traversal patterns
        if (str_contains($filename, '..') || str_contains($filename, "\0")) {
            throw new \InvalidArgumentException('Invalid filename: path traversal detected');
        }

        // Reject absolute paths
        if (str_starts_with($filename, '/') || preg_match('/^[a-zA-Z]:/', $filename)) {
            throw new \InvalidArgumentException('Invalid filename: absolute path not allowed');
        }

        // Get just the basename (removes directory components)
        $basename = basename($filename);

        if ($basename === '' || $basename === '.' || $basename === '..') {
            throw new \InvalidArgumentException('Invalid filename');
        }

        return $basename;
    }

    /**
     * Collect files by extension from source to target directory.
     *
     * @param string[] $extensions
     *
     * @return string[] Collected filenames
     */
    private function collectFilesByExtension(
        string $sourcePath,
        string $targetPath,
        array $extensions,
        Filesystem $filesystem,
    ): array {
        $collected = [];

        $finder = new Finder();
        $finder->files()->in($sourcePath)->depth(0);

        foreach ($extensions as $ext) {
            $finder->name('*.' . $ext);
        }

        foreach ($finder as $file) {
            if ($file->getSize() > self::MAX_ARTIFACT_SIZE) {
                $this->logger->warning('Artifact too large, skipping', [
                    'file' => $file->getFilename(),
                    'size' => $file->getSize(),
                ]);

                continue;
            }

            $targetFile = $targetPath . '/' . $file->getFilename();
            $filesystem->copy($file->getRealPath(), $targetFile, true);
            $collected[] = $file->getFilename();
        }

        return $collected;
    }
}
