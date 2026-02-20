<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\FileUploadService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadServiceTest extends TestCase
{
    public function testGetAllowedMimeTypesIncludesImageTypes(): void
    {
        $types = $this->createService()->getAllowedMimeTypes();

        $this->assertContains('image/jpeg', $types);
        $this->assertContains('image/png', $types);
        $this->assertContains('image/gif', $types);
        $this->assertContains('image/webp', $types);
    }

    public function testGetAllowedMimeTypesIncludesDocumentTypes(): void
    {
        $types = $this->createService()->getAllowedMimeTypes();

        $this->assertContains('application/pdf', $types);
        $this->assertContains('text/plain', $types);
        $this->assertContains('text/csv', $types);
    }

    public function testGetAllowedMimeTypesIncludesArchiveTypes(): void
    {
        $types = $this->createService()->getAllowedMimeTypes();

        $this->assertContains('application/zip', $types);
        $this->assertContains('application/x-7z-compressed', $types);
    }

    public function testIsImageTypeReturnsTrueForImage(): void
    {
        $this->assertTrue($this->createService()->isImageType('image/jpeg'));
    }

    public function testIsImageTypeReturnsFalseForDocument(): void
    {
        $this->assertFalse($this->createService()->isImageType('application/pdf'));
    }

    public function testIsDocumentTypeReturnsTrueForDocument(): void
    {
        $this->assertTrue($this->createService()->isDocumentType('application/pdf'));
    }

    public function testIsDocumentTypeReturnsFalseForImage(): void
    {
        $this->assertFalse($this->createService()->isDocumentType('image/jpeg'));
    }

    public function testGetPublicUrlPrependsUploadsPath(): void
    {
        $this->assertSame('/uploads/images/photo.jpg', $this->createService()->getPublicUrl('images/photo.jpg'));
    }

    public function testGetPublicUrlHandlesLeadingSlash(): void
    {
        $this->assertSame('/uploads/images/photo.jpg', $this->createService()->getPublicUrl('/images/photo.jpg'));
    }

    public function testFileExistsDelegatesToStorage(): void
    {
        $uploads = $this->createMock(FilesystemOperator::class);
        $uploads->method('fileExists')->with('test/file.jpg')->willReturn(true);

        $this->assertTrue($this->createService(uploads: $uploads)->fileExists('test/file.jpg'));
    }

    public function testDeletePublicFileReturnsTrueWhenExists(): void
    {
        $uploads = $this->createMock(FilesystemOperator::class);
        $uploads->method('fileExists')->with('images/photo.jpg')->willReturn(true);
        $uploads->expects($this->once())->method('delete')->with('images/photo.jpg');

        $this->assertTrue($this->createService(uploads: $uploads)->deletePublicFile('images/photo.jpg'));
    }

    public function testDeletePublicFileReturnsFalseWhenMissing(): void
    {
        $uploads = $this->createMock(FilesystemOperator::class);
        $uploads->method('fileExists')->with('missing.jpg')->willReturn(false);
        $uploads->expects($this->never())->method('delete');

        $this->assertFalse($this->createService(uploads: $uploads)->deletePublicFile('missing.jpg'));
    }

    public function testDeletePrivateFileReturnsTrueWhenExists(): void
    {
        $documents = $this->createMock(FilesystemOperator::class);
        $documents->method('fileExists')->with('docs/report.pdf')->willReturn(true);
        $documents->expects($this->once())->method('delete')->with('docs/report.pdf');

        $this->assertTrue($this->createService(documents: $documents)->deletePrivateFile('docs/report.pdf'));
    }

    public function testDeletePrivateFileReturnsFalseWhenMissing(): void
    {
        $documents = $this->createMock(FilesystemOperator::class);
        $documents->method('fileExists')->with('missing.pdf')->willReturn(false);
        $documents->expects($this->never())->method('delete');

        $this->assertFalse($this->createService(documents: $documents)->deletePrivateFile('missing.pdf'));
    }

    private function createService(
        ?FilesystemOperator $uploads = null,
        ?FilesystemOperator $documents = null,
    ): FileUploadService {
        return new FileUploadService(
            $uploads ?? $this->createStub(FilesystemOperator::class),
            $documents ?? $this->createStub(FilesystemOperator::class),
            $this->createStub(SluggerInterface::class),
        );
    }
}
