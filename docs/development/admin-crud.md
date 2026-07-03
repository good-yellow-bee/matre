# Admin CRUD Pattern

Admin features are built as a JSON API controller (`src/Controller/Api/`) plus SPA views (`assets/spa/views/`). There are no server-rendered forms, flash messages, or redirects — the browser only ever loads the SPA shell, and all reads/writes go through `/api`.

## API Controller Pattern

Controllers follow this shape (from `src/Controller/Api/TestEnvironmentApiController.php`):

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\YourEntity;
use App\Repository\YourEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/your-entities')]
#[IsGranted('ROLE_ADMIN')]
class YourEntityApiController extends AbstractController
{
    public function __construct(
        private readonly YourEntityRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/list', name: 'api_your_entities_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map(
            fn (YourEntity $entity) => $this->serializeEntity($entity),
            $this->repository->findAllOrdered(),
        ));
    }

    #[Route('/{id}', name: 'api_your_entities_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $entity = $this->repository->find($id);

        if (!$entity) {
            return $this->json(['error' => 'Entity not found'], 404);
        }

        return $this->json($this->serializeEntity($entity, true));
    }

    #[Route('', name: 'api_your_entities_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateEntityData($data);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $entity = new YourEntity();
        $this->populateEntity($entity, $data);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Entity created successfully',
            'id' => $entity->getId(),
        ], 201);
    }

    #[Route('/{id}', name: 'api_your_entities_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $entity = $this->repository->find($id);

        if (!$entity) {
            return $this->json(['error' => 'Entity not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $errors = $this->validateEntityData($data, $entity);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $this->populateEntity($entity, $data);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Entity updated successfully']);
    }

    #[Route('/{id}/toggle-active', name: 'api_your_entities_toggle_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleActive(int $id): JsonResponse
    {
        $entity = $this->repository->find($id);

        if (!$entity) {
            return $this->json(['error' => 'Entity not found'], 404);
        }

        $entity->setIsActive(!$entity->getIsActive());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isActive' => $entity->getIsActive(),
            'message' => sprintf('Entity "%s" %s', $entity->getName(), $entity->getIsActive() ? 'activated' : 'deactivated'),
        ]);
    }

    #[Route('/{id}', name: 'api_your_entities_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $entity = $this->repository->find($id);

        if (!$entity) {
            return $this->json(['error' => 'Entity not found'], 404);
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Entity deleted']);
    }

    /** @return array<string, string> */
    private function validateEntityData(array $data, ?YourEntity $existing = null): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        // ... length checks, uniqueness queries (exclude $existing on update)

        return $errors;
    }

    private function serializeEntity(YourEntity $entity, bool $detail = false): array
    {
        $data = [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'isActive' => $entity->getIsActive(),
            'createdAt' => $entity->getCreatedAt()->format('c'),
            'updatedAt' => $entity->getUpdatedAt()?->format('c'),
        ];

        if ($detail) {
            $data['description'] = $entity->getDescription();
        }

        return $data;
    }
}
```

Key conventions:

- **Class-level** `#[Route('/api/{plural}')]` + `#[IsGranted('ROLE_ADMIN')]` (use `ROLE_USER` at class level and per-method `ROLE_ADMIN` when some endpoints are user-accessible, as in `TestRunApiController`).
- **Request body:** `json_decode($request->getContent(), true) ?? []` — no Form types.
- **Validation:** a private `validateXData()` returning a `field => message` map; respond `422` with `['errors' => $errors]`.
- **Serialization:** a private `serializeX(X $x, bool $detail = false): array` — explicit field lists, never `$this->json($entity)` directly (avoids leaking credentials/secrets).
- **Errors:** `['error' => '...']` with 404/400; **success:** `['success' => true, 'message' => '...']` (+ `id` and 201 on create).
- **Async field validation** endpoints like `POST /validate-name` / `POST /validate-code` return `['valid' => bool, 'message' => '...']` for inline form feedback.
- Some resources expose both `GET ''` (lightweight list for dropdowns) and `GET /list` (full grid payload).

## Route Conventions

| Route | Name | Method | Purpose |
|-------|------|--------|---------|
| `/api/entities` | `api_entities_list` | GET | List (dropdown/lightweight) |
| `/api/entities/list` | `api_entities_grid` | GET | Grid payload (optional) |
| `/api/entities/{id}` | `api_entities_get` | GET | Single resource |
| `/api/entities` | `api_entities_create` | POST | Create (201) |
| `/api/entities/{id}` | `api_entities_update` | PUT | Update |
| `/api/entities/{id}` | `api_entities_delete` | DELETE | Delete |
| `/api/entities/{id}/toggle-active` | `api_entities_toggle_active` | POST | Toggle status |
| `/api/entities/validate-name` | `api_entities_validate_name` | POST | Async validation |

## CSRF Protection

CSRF is handled centrally — controllers contain **no** token checks. `App\EventListener\ApiCsrfListener` (kernel.request listener) validates the `X-CSRF-Token` header on every mutating `/api` request; the SPA API client attaches the token automatically. New endpoints get CSRF protection for free as long as they live under `/api`.

## SPA View Pattern

Views live in `assets/spa/views/{feature}/` and use the shared UI kit (see [SPA Frontend](spa-frontend.md)):

```vue
<template>
  <div>
    <PageHeader title="Entities" subtitle="What this page manages">
      <template #actions>
        <RouterLink class="btn-primary" :to="{ name: 'entity-new' }">Add Entity</RouterLink>
      </template>
    </PageHeader>

    <DataTable :columns="columns" :rows="entities" :loading="loading">
      <template #cell-actions="{ row }">
        <button class="btn-danger btn-sm" @click="askDelete(row)">Delete</button>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import PageHeader from '../../components/ui/PageHeader.vue';
import DataTable from '../../components/ui/DataTable.vue';
import { api } from '../../api/client';
import { useToastStore } from '../../stores/toasts';
import { confirm } from '../../composables/useConfirm';

const toasts = useToastStore();

const columns = [
  { key: 'name', label: 'Name' },
  { key: 'actions', label: 'Actions', headerClass: 'text-right', cellClass: 'text-right' },
];

const entities = ref([]);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    entities.value = await api.get('/api/entities/list');
  } catch (e) {
    toasts.error(e.message);
  } finally {
    loading.value = false;
  }
}

function askDelete(entity) {
  confirm({
    title: 'Delete entity?',
    message: `Delete entity “${entity.name}”?`,
    confirmLabel: 'Delete',
    danger: true,
    action: async () => {
      const result = await api.delete(`/api/entities/${entity.id}`);
      entities.value = entities.value.filter((item) => item.id !== entity.id);
      toasts.success(result.message);
    },
  });
}

onMounted(load);
</script>
```

`confirm()` (named export from `composables/useConfirm.js`) opens the global `ConfirmDialog` and runs `action` when the user confirms — thrown errors are toasted automatically, so the action only needs the happy path.

Form views (`{Feature}FormView.vue`) handle both create and edit (route param decides), submit via `api.post`/`api.put`, map a 422 `errors` payload onto per-field error state, and navigate back with `router.push` on success.

## Checklist

When creating a new admin feature:

1. [ ] Create entity (`src/Entity/`) + repository (`src/Repository/`)
2. [ ] Create API controller (`src/Controller/Api/{Feature}ApiController.php`)
3. [ ] Create views (`assets/spa/views/{feature}/`) with feature-private `components/`
4. [ ] Register routes in `assets/spa/router/index.js` (+ sidebar entry in `AppSidebar.vue`)
5. [ ] Write tests (`tests/Functional/Controller/Api/`, optionally a Playwright spec)
6. [ ] Rebuild frontend: `npm run build`
