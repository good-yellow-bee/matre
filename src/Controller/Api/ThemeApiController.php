<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/themes')]
#[IsGranted('ROLE_ADMIN')]
class ThemeApiController extends AbstractController
{
    #[Route('/list', name: 'api_themes_list', methods: ['GET'])]
    public function list(Request $request, ThemeRepository $themes): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $sort = $request->query->get('sort', 'name');
        $order = $request->query->get('order', 'asc');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = max(1, min(100, (int) $request->query->get('perPage', 20)));

        $validSorts = ['name', 'isActive', 'isDefault', 'createdAt'];
        if (!in_array($sort, $validSorts, true)) {
            $sort = 'name';
        }

        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $themes->createQueryBuilder('t')
            ->select('t', 'COUNT(u.id) as HIDDEN userCount')
            ->leftJoin('t.users', 'u')
            ->groupBy('t.id')
            ->orderBy('t.' . $sort, $order);

        if ('' !== $search) {
            $qb
                ->andWhere('LOWER(t.name) LIKE :search OR LOWER(t.description) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        // Count total
        $countQb = clone $qb;
        $total = count($countQb->getQuery()->getResult());

        // Paginate
        $results = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $data = array_map(static fn ($theme) => [
            'id' => $theme->getId(),
            'name' => $theme->getName(),
            'description' => $theme->getDescription(),
            'primaryColor' => $theme->getPrimaryColor(),
            'secondaryColor' => $theme->getSecondaryColor(),
            'isActive' => $theme->getIsActive(),
            'isDefault' => $theme->getIsDefault(),
            'userCount' => count($theme->getUsers()),
            'createdAt' => $theme->getCreatedAt()->format('c'),
        ], $results);

        return $this->json([
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }
}
