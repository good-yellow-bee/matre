<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class SlugGeneratorTest extends TestCase
{
    private SluggerInterface $slugger;

    private EntityManagerInterface $entityManager;

    private SlugGenerator $service;

    protected function setUp(): void
    {
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new SlugGenerator($this->slugger, $this->entityManager);
    }

    public function testSlugifyConvertsToLowercase(): void
    {
        $unicodeString = $this->createStub(UnicodeString::class);
        $unicodeString->method('lower')->willReturnSelf();
        $unicodeString->method('toString')->willReturn('hello-world');

        $this->slugger
            ->method('slug')
            ->willReturn($unicodeString);

        $result = $this->service->slugify('Hello World');

        $this->assertSame('hello-world', $result);
    }

    public function testSlugifyTruncatesLongText(): void
    {
        $longText = str_repeat('a', 300);
        $unicodeString = $this->createStub(UnicodeString::class);
        $unicodeString->method('lower')->willReturnSelf();
        $unicodeString->method('toString')->willReturn($longText);

        $this->slugger
            ->method('slug')
            ->willReturn($unicodeString);

        $result = $this->service->slugify($longText, 100);

        $this->assertSame(100, strlen($result));
    }

    public function testIsValidSlugReturnsTrueForValidSlug(): void
    {
        $this->assertTrue($this->service->isValidSlug('valid-slug-123'));
        $this->assertTrue($this->service->isValidSlug('test'));
        $this->assertTrue($this->service->isValidSlug('a-b-c'));
    }

    public function testIsValidSlugReturnsFalseForInvalidSlug(): void
    {
        $this->assertFalse($this->service->isValidSlug('Invalid-Slug')); // Uppercase
        $this->assertFalse($this->service->isValidSlug('-invalid')); // Starts with hyphen
        $this->assertFalse($this->service->isValidSlug('invalid-')); // Ends with hyphen
        $this->assertFalse($this->service->isValidSlug('invalid slug')); // Space
        $this->assertFalse($this->service->isValidSlug('invalid_slug')); // Underscore
        $this->assertFalse($this->service->isValidSlug('invalid--slug')); // Double hyphen
    }

    public function testGenerateFromPartsCombinesAndSlugifies(): void
    {
        $unicodeString = $this->createStub(UnicodeString::class);
        $unicodeString->method('lower')->willReturnSelf();
        $unicodeString->method('toString')->willReturn('category-my-title');

        $this->slugger
            ->method('slug')
            ->willReturn($unicodeString);

        $result = $this->service->generateFromParts(['Category', 'My Title']);

        $this->assertSame('category-my-title', $result);
    }

    public function testGenerateFromPartsFiltersEmptyParts(): void
    {
        $unicodeString = $this->createStub(UnicodeString::class);
        $unicodeString->method('lower')->willReturnSelf();
        $unicodeString->method('toString')->willReturn('part1-part2');

        $this->slugger
            ->method('slug')
            ->willReturn($unicodeString);

        $result = $this->service->generateFromParts(['Part1', '', 'Part2']);

        $this->assertSame('part1-part2', $result);
    }

    public function testGenerateUniqueSlugWithoutCollision(): void
    {
        $unicodeString = $this->createStub(UnicodeString::class);
        $unicodeString->method('lower')->willReturnSelf();
        $unicodeString->method('toString')->willReturn('my-slug');

        $this->slugger
            ->method('slug')
            ->willReturn($unicodeString);

        // Mock repository to return 0 (slug doesn't exist)
        $this->mockSlugExistenceCheck(0);

        $result = $this->service->generateUniqueSlug('My Slug', 'App\Entity\TestEntity');

        $this->assertSame('my-slug', $result);
    }

    public function testGenerateUniqueSlugWithCollision(): void
    {
        $unicodeString = $this->createStub(UnicodeString::class);
        $unicodeString->method('lower')->willReturnSelf();
        $unicodeString->method('toString')->willReturn('my-slug');

        $this->slugger
            ->method('slug')
            ->willReturn($unicodeString);

        // First check: slug exists, second check: slug-1 doesn't exist
        $query = $this->createStub(Query::class);
        $query->method('getSingleScalarResult')
            ->willReturnOnConsecutiveCalls(1, 0);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $result = $this->service->generateUniqueSlug('My Slug', 'App\Entity\TestEntity');

        $this->assertSame('my-slug-1', $result);
    }

    public function testGenerateUniqueSlugExcludesEntityId(): void
    {
        $unicodeString = $this->createStub(UnicodeString::class);
        $unicodeString->method('lower')->willReturnSelf();
        $unicodeString->method('toString')->willReturn('my-slug');

        $this->slugger
            ->method('slug')
            ->willReturn($unicodeString);

        $query = $this->createStub(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);

        $result = $this->service->generateUniqueSlug('My Slug', 'App\Entity\TestEntity', 123);

        $this->assertSame('my-slug', $result);
    }

    private function mockSlugExistenceCheck(int $count): void
    {
        $query = $this->createStub(Query::class);
        $query->method('getSingleScalarResult')->willReturn($count);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($repository);
    }
}
