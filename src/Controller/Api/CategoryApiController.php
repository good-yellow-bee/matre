<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/categories')]
#[IsGranted('ROLE_USER')]
class CategoryApiController extends AbstractController
{
    #[Route('', name: 'api_categories_index', methods: ['GET'])]
    public function index(Request $request, CategoryRepository $categories): JsonResponse
    {
        $search = trim((string) $request->query->get('q', ''));

        $qb = $categories->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC');

        if ('' !== $search) {
            $qb
                ->andWhere('LOWER(c.name) LIKE :q OR LOWER(c.slug) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($search).'%');
        }

        $results = $qb
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $items = array_map(static fn ($category) => [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
        ], $results);

        return $this->json(['items' => $items]);
    }
}
