# Notification Templates Feature Specification

## Overview

This feature allows administrators to customize notification templates (Slack and Email) sent after test runs complete. Currently, templates are hardcoded in `NotificationService.php`. This feature will make them configurable via the admin interface.

## Goals

1. Allow admins to customize notification message content for Slack and Email
2. Support template variables (placeholders) for dynamic content
3. Provide a Vue-based editor with live preview
4. Maintain backward compatibility with existing notification flow

---

## 1. Entity Design

### NotificationTemplate Entity

**Table:** `matre_notification_templates`

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Primary key |
| `channel` | string(20) | `slack` or `email` |
| `name` | string(100) | Template identifier (e.g., `test_run_completed`, `test_run_failed`) |
| `subject` | string(255) | Email subject line (nullable for Slack) |
| `body` | text | Template body content |
| `isActive` | bool | Enable/disable template |
| `isDefault` | bool | Mark as system default (non-deletable) |
| `createdAt` | datetime_immutable | Creation timestamp |
| `updatedAt` | datetime_immutable | Last update timestamp |

**Template Names (Event Types):**

| Name | Trigger Condition |
|------|-------------------|
| `test_run_completed_success` | Run completed with 0 failures |
| `test_run_completed_failures` | Run completed with failures > 0 |
| `test_run_failed` | Run failed (execution error) |
| `test_run_cancelled` | Run was cancelled |

**Unique Constraint:** (`channel`, `name`) - one template per channel per event type.

### Entity Code

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationTemplateRepository::class)]
#[ORM\Table(name: 'matre_notification_templates')]
#[ORM\UniqueConstraint(name: 'UNIQ_CHANNEL_NAME', columns: ['channel', 'name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['channel', 'name'], message: 'A template for this channel and event already exists.')]
class NotificationTemplate
{
    public const CHANNEL_SLACK = 'slack';
    public const CHANNEL_EMAIL = 'email';

    public const NAME_COMPLETED_SUCCESS = 'test_run_completed_success';
    public const NAME_COMPLETED_FAILURES = 'test_run_completed_failures';
    public const NAME_FAILED = 'test_run_failed';
    public const NAME_CANCELLED = 'test_run_cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::CHANNEL_SLACK, self::CHANNEL_EMAIL])]
    private string $channel;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::NAME_COMPLETED_SUCCESS,
        self::NAME_COMPLETED_FAILURES,
        self::NAME_FAILED,
        self::NAME_CANCELLED,
    ])]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Template body cannot be empty.')]
    private string $body;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ... getters/setters with fluent interface
}
```

---

## 2. Template Variables

Variables use `{{ variable }}` syntax (Twig-like but processed in PHP).

### Available Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `{{ run_id }}` | Test run ID | `123` |
| `{{ run_status }}` | Status (Completed, Failed, Cancelled) | `Completed` |
| `{{ environment_name }}` | Test environment name | `stage-us` |
| `{{ test_type }}` | Test type (MFTF, Playwright, Both) | `MFTF` |
| `{{ duration }}` | Formatted duration | `5m 23s` |
| `{{ triggered_by }}` | Who triggered (Scheduler, Manual, API) | `Scheduler` |
| `{{ test_filter }}` | Applied filter (if any) | `@smoke` |
| `{{ passed_count }}` | Passed test count | `95` |
| `{{ failed_count }}` | Failed test count | `2` |
| `{{ broken_count }}` | Broken test count | `1` |
| `{{ skipped_count }}` | Skipped test count | `3` |
| `{{ total_count }}` | Total test count | `101` |
| `{{ error_message }}` | Error message (if failed) | `Docker timeout` |
| `{{ allure_report_url }}` | Link to Allure report | `https://...` |
| `{{ site_name }}` | Site name from Settings | `MATRE` |

### Slack-Specific Variables

| Variable | Description |
|----------|-------------|
| `{{ status_emoji }}` | Status emoji (`:white_check_mark:`, `:warning:`, `:x:`) |
| `{{ status_color }}` | Attachment color (`good`, `warning`, `danger`) |

### Conditional Blocks

Support simple conditionals for optional content:

```
{% if has_failures %}
Failed tests require attention!
{% endif %}

{% if has_filter %}
Filter applied: {{ test_filter }}
{% endif %}

{% if has_error %}
Error: {{ error_message }}
{% endif %}
```

---

## 3. Default Templates

### Email - Completed Success

**Subject:** `{{ status_emoji }} Test Run #{{ run_id }} - {{ run_status }} ({{ environment_name }})`

**Body:**
```html
<html>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
<h2>Test Run #{{ run_id }}</h2>

<table style="width: 100%; border-collapse: collapse;">
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Environment</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ environment_name }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Type</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_type }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Status</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ run_status }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Duration</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ duration }}</td></tr>
  <tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Triggered By</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ triggered_by }}</td></tr>
  {% if has_filter %}<tr><td style="padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;">Filter</td><td style="padding: 8px; border-bottom: 1px solid #eee;">{{ test_filter }}</td></tr>{% endif %}
</table>

{% if total_count > 0 %}
<h3>Results</h3>
<ul>
  <li>Passed: {{ passed_count }}</li>
  <li>Failed: {{ failed_count }}</li>
  <li>Skipped: {{ skipped_count }}</li>
  <li>Broken: {{ broken_count }}</li>
</ul>
{% endif %}

{% if allure_report_url %}
<p><a href="{{ allure_report_url }}">View Allure Report</a></p>
{% endif %}

<hr>
<p style="color: #666; font-size: 12px;">{{ site_name }} - Automation Test Runner</p>
</body>
</html>
```

### Slack - Completed Success

**Body (JSON structure described):**
```
{{ status_emoji }} Test Run #{{ run_id }} {{ run_status }}

*Environment:* {{ environment_name }}
*Type:* {{ test_type }}
*Status:* {{ run_status }}
*Duration:* {{ duration }}

{% if total_count > 0 %}
*Results:* {{ passed_count }} passed | {{ failed_count }} failed | {{ broken_count }} broken | {{ skipped_count }} skipped
{% endif %}

{% if has_filter %}
*Filter:* {{ test_filter }}
{% endif %}

{% if allure_report_url %}
<{{ allure_report_url }}|View Allure Report>
{% endif %}
```

---

## 4. Admin Controller

### Routes

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/admin/notification-templates` | GET | `admin_notification_template_index` | List all templates |
| `/admin/notification-templates/{id}/edit` | GET | `admin_notification_template_edit` | Edit form (Vue) |
| `/admin/notification-templates/{id}/toggle-active` | POST | `admin_notification_template_toggle_active` | Toggle active status |
| `/admin/notification-templates/reset-defaults` | POST | `admin_notification_template_reset_defaults` | Reset to defaults |

### API Endpoints

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/api/notification-templates` | GET | `api_notification_template_index` | List templates |
| `/api/notification-templates/{id}` | GET | `api_notification_template_show` | Get single template |
| `/api/notification-templates/{id}` | PUT | `api_notification_template_update` | Update template |
| `/api/notification-templates/{id}/preview` | POST | `api_notification_template_preview` | Preview with sample data |
| `/api/notification-templates/variables` | GET | `api_notification_template_variables` | List available variables |

### Controller Structure

```php
#[Route('/admin/notification-templates')]
#[IsGranted('ROLE_ADMIN')]
class NotificationTemplateController extends AbstractController
{
    #[Route('', name: 'admin_notification_template_index', methods: ['GET'])]
    public function index(NotificationTemplateRepository $repository): Response
    {
        $templates = $repository->findAllGroupedByChannel();
        return $this->render('admin/notification_template/index.html.twig', [
            'templates' => $templates,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_notification_template_edit', methods: ['GET'])]
    public function edit(NotificationTemplate $template): Response
    {
        return $this->render('admin/notification_template/edit.html.twig', [
            'template' => $template,
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'admin_notification_template_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, NotificationTemplate $template, EntityManagerInterface $em): Response
    {
        // CSRF validation + toggle
    }

    #[Route('/reset-defaults', name: 'admin_notification_template_reset_defaults', methods: ['POST'])]
    public function resetDefaults(Request $request, NotificationTemplateService $service): Response
    {
        // Reset all templates to defaults
    }
}
```

---

## 5. Vue Components

### File Structure

```
assets/vue/
├── notification-template-form-app.js     (Entry point)
├── components/
│   └── NotificationTemplateForm.vue      (Main form component)
└── composables/
    └── useNotificationTemplateForm.js    (Form logic)
```

### NotificationTemplateForm.vue

Features:
1. **Split-pane editor**: Template editor on left, live preview on right
2. **Variable insertion toolbar**: Clickable buttons to insert variables
3. **Syntax highlighting**: For template variables
4. **Live preview**: Renders template with sample data
5. **Validation**: Required fields, template syntax validation

```vue
<template>
  <div class="template-editor">
    <!-- Header with template info -->
    <div class="editor-header">
      <span class="badge" :class="channelBadgeClass">{{ template.channel }}</span>
      <span class="template-name">{{ templateNameLabel }}</span>
      <span class="badge" :class="{ 'bg-success': template.isActive, 'bg-secondary': !template.isActive }">
        {{ template.isActive ? 'Active' : 'Inactive' }}
      </span>
    </div>

    <form @submit.prevent="handleSubmit">
      <!-- Subject (Email only) -->
      <div v-if="isEmail" class="mb-3">
        <label class="form-label">Subject</label>
        <input v-model="form.subject" type="text" class="form-control" />
        <div class="form-text">Use variables like {{ '{{ run_id }}' }} for dynamic content</div>
      </div>

      <!-- Variable Toolbar -->
      <div class="variable-toolbar mb-3">
        <label class="form-label">Insert Variable:</label>
        <div class="btn-group flex-wrap">
          <button
            v-for="variable in availableVariables"
            :key="variable.name"
            type="button"
            class="btn btn-sm btn-outline-secondary"
            @click="insertVariable(variable.name)"
            :title="variable.description"
          >
            {{ variable.name }}
          </button>
        </div>
      </div>

      <!-- Split Editor/Preview -->
      <div class="row">
        <div class="col-md-6">
          <label class="form-label">Template Body</label>
          <textarea
            v-model="form.body"
            class="form-control template-textarea"
            rows="20"
            ref="bodyTextarea"
          ></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">
            Preview
            <button type="button" class="btn btn-sm btn-link" @click="refreshPreview">
              <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
          </label>
          <div class="preview-container" v-html="previewHtml"></div>
        </div>
      </div>

      <!-- Actions -->
      <div class="form-actions mt-4">
        <button type="submit" class="btn btn-primary" :disabled="submitting">
          {{ submitting ? 'Saving...' : 'Save Template' }}
        </button>
        <a :href="cancelUrl" class="btn btn-secondary">Cancel</a>
        <button type="button" class="btn btn-outline-warning" @click="resetToDefault">
          Reset to Default
        </button>
      </div>
    </form>
  </div>
</template>
```

### Composable: useNotificationTemplateForm.js

```javascript
import { reactive, ref, computed, watch } from 'vue';
import { debounce } from '../utils/debounce.js';

export function useNotificationTemplateForm(apiUrl, templateId) {
  const form = reactive({
    subject: '',
    body: '',
    isActive: true,
  });

  const template = ref(null);
  const previewHtml = ref('');
  const availableVariables = ref([]);
  const loading = ref(false);
  const submitting = ref(false);
  const errors = reactive({});

  // Fetch template data
  const fetchTemplate = async () => {
    loading.value = true;
    try {
      const response = await fetch(`${apiUrl}/${templateId}`);
      const data = await response.json();
      template.value = data;
      form.subject = data.subject || '';
      form.body = data.body;
      form.isActive = data.isActive;
    } finally {
      loading.value = false;
    }
  };

  // Fetch available variables
  const fetchVariables = async () => {
    const response = await fetch(`${apiUrl}/variables`);
    availableVariables.value = await response.json();
  };

  // Generate preview with debounce
  const generatePreview = debounce(async () => {
    try {
      const response = await fetch(`${apiUrl}/${templateId}/preview`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          subject: form.subject,
          body: form.body,
        }),
      });
      const data = await response.json();
      previewHtml.value = data.html;
    } catch (error) {
      previewHtml.value = '<div class="text-danger">Preview error</div>';
    }
  }, 500);

  // Watch form changes for live preview
  watch([() => form.subject, () => form.body], generatePreview);

  // Save template
  const saveTemplate = async () => {
    submitting.value = true;
    try {
      const response = await fetch(`${apiUrl}/${templateId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          subject: form.subject,
          body: form.body,
        }),
      });

      if (response.ok) {
        return { success: true, message: 'Template saved successfully' };
      } else {
        const data = await response.json();
        return { success: false, message: data.error };
      }
    } finally {
      submitting.value = false;
    }
  };

  return {
    form, template, previewHtml, availableVariables,
    loading, submitting, errors,
    fetchTemplate, fetchVariables, generatePreview, saveTemplate,
  };
}
```

---

## 6. Service Changes

### NotificationTemplateService (New)

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationTemplate;
use App\Entity\TestRun;
use App\Repository\NotificationTemplateRepository;

class NotificationTemplateService
{
    public function __construct(
        private readonly NotificationTemplateRepository $repository,
        private readonly string $allurePublicUrl,
        private readonly string $siteName,
    ) {}

    /**
     * Get rendered template for a test run.
     */
    public function render(TestRun $run, string $channel, string $eventName): array
    {
        $template = $this->repository->findOneBy([
            'channel' => $channel,
            'name' => $eventName,
            'isActive' => true,
        ]);

        if (!$template) {
            // Return null to fall back to hardcoded defaults
            return ['subject' => null, 'body' => null];
        }

        $variables = $this->buildVariables($run);

        return [
            'subject' => $this->replaceVariables($template->getSubject() ?? '', $variables),
            'body' => $this->replaceVariables($template->getBody(), $variables),
        ];
    }

    /**
     * Build variable map from TestRun.
     */
    public function buildVariables(TestRun $run): array
    {
        $counts = $run->getResultCounts();
        $env = $run->getEnvironment();

        return [
            'run_id' => (string) $run->getId(),
            'run_status' => ucfirst($run->getStatus()),
            'environment_name' => $env->getName(),
            'test_type' => strtoupper($run->getType()),
            'duration' => $run->getDurationFormatted(),
            'triggered_by' => ucfirst($run->getTriggeredBy()),
            'test_filter' => $run->getTestFilter() ?? '',
            'passed_count' => (string) ($counts['passed'] ?? 0),
            'failed_count' => (string) ($counts['failed'] ?? 0),
            'broken_count' => (string) ($counts['broken'] ?? 0),
            'skipped_count' => (string) ($counts['skipped'] ?? 0),
            'total_count' => (string) ($counts['total'] ?? 0),
            'error_message' => $run->getErrorMessage() ?? '',
            'allure_report_url' => $this->getAllureReportUrl($env->getName()),
            'site_name' => $this->siteName,
            'status_emoji' => $this->getStatusEmoji($run),
            'status_color' => $this->getStatusColor($run),
            // Conditional flags
            'has_failures' => ($counts['failed'] ?? 0) > 0,
            'has_filter' => !empty($run->getTestFilter()),
            'has_error' => !empty($run->getErrorMessage()),
        ];
    }

    /**
     * Replace {{ variable }} placeholders.
     */
    private function replaceVariables(string $template, array $variables): string
    {
        // Replace simple variables: {{ var_name }}
        $result = preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn ($m) => $variables[$m[1]] ?? '',
            $template
        );

        // Process conditionals: {% if condition %}...{% endif %}
        $result = preg_replace_callback(
            '/\{%\s*if\s+([a-z_]+)\s*%\}(.*?)\{%\s*endif\s*%\}/s',
            fn ($m) => !empty($variables[$m[1]]) ? $m[2] : '',
            $result
        );

        return $result;
    }

    /**
     * Get sample variables for preview.
     */
    public function getSampleVariables(): array
    {
        return [
            'run_id' => '123',
            'run_status' => 'Completed',
            'environment_name' => 'stage-us',
            'test_type' => 'MFTF',
            'duration' => '5m 23s',
            'triggered_by' => 'Scheduler',
            'test_filter' => '@smoke',
            'passed_count' => '95',
            'failed_count' => '2',
            'broken_count' => '1',
            'skipped_count' => '3',
            'total_count' => '101',
            'error_message' => '',
            'allure_report_url' => 'https://allure.example.com/report',
            'site_name' => $this->siteName,
            'status_emoji' => ':warning:',
            'status_color' => 'warning',
            'has_failures' => true,
            'has_filter' => true,
            'has_error' => false,
        ];
    }

    /**
     * Create default templates (for initial setup/reset).
     */
    public function createDefaults(): void
    {
        // Create 8 templates: 4 events x 2 channels
        // ... implementation
    }
}
```

### Modified NotificationService

```php
public function sendSlackNotification(TestRun $run): void
{
    // Determine event name
    $eventName = $this->getEventName($run);

    // Try to get custom template
    $rendered = $this->templateService->render($run, 'slack', $eventName);

    if ($rendered['body']) {
        $message = $this->buildSlackMessageFromTemplate($run, $rendered['body']);
    } else {
        // Fall back to hardcoded default
        $message = $this->buildSlackMessage($run);
    }

    // ... send logic
}

private function getEventName(TestRun $run): string
{
    return match ($run->getStatus()) {
        TestRun::STATUS_COMPLETED => ($run->getResultCounts()['failed'] ?? 0) > 0
            ? NotificationTemplate::NAME_COMPLETED_FAILURES
            : NotificationTemplate::NAME_COMPLETED_SUCCESS,
        TestRun::STATUS_FAILED => NotificationTemplate::NAME_FAILED,
        TestRun::STATUS_CANCELLED => NotificationTemplate::NAME_CANCELLED,
        default => NotificationTemplate::NAME_COMPLETED_SUCCESS,
    };
}
```

---

## 7. Database Migration

```php
public function up(Schema $schema): void
{
    $this->addSql('CREATE TABLE matre_notification_templates (
        id INT AUTO_INCREMENT NOT NULL,
        channel VARCHAR(20) NOT NULL,
        name VARCHAR(100) NOT NULL,
        subject VARCHAR(255) DEFAULT NULL,
        body LONGTEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
        updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
        UNIQUE INDEX UNIQ_CHANNEL_NAME (channel, name),
        PRIMARY KEY(id)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
}
```

---

## 8. Admin UI Templates

### Index Page (templates grouped by channel)

```
/admin/notification-templates
├── Slack Templates
│   ├── Test Run Completed (Success) [Edit] [Active/Inactive]
│   ├── Test Run Completed (Failures) [Edit] [Active/Inactive]
│   ├── Test Run Failed [Edit] [Active/Inactive]
│   └── Test Run Cancelled [Edit] [Active/Inactive]
├── Email Templates
│   ├── Test Run Completed (Success) [Edit] [Active/Inactive]
│   ├── Test Run Completed (Failures) [Edit] [Active/Inactive]
│   ├── Test Run Failed [Edit] [Active/Inactive]
│   └── Test Run Cancelled [Edit] [Active/Inactive]
└── [Reset All to Defaults]
```

### Edit Page (Vue island)

```
/admin/notification-templates/1/edit
├── Header: [Slack Badge] Test Run Completed (Success) [Active Badge]
├── Variable Toolbar: [run_id] [run_status] [environment_name] ...
├── Split Pane:
│   ├── Left: Template Editor (textarea with syntax hints)
│   └── Right: Live Preview (rendered HTML/text)
└── Actions: [Save] [Cancel] [Reset to Default]
```

---

## 9. Navigation Integration

Add to admin sidebar under "Administration" section:

```twig
<a href="{{ path('admin_notification_template_index') }}"
   class="{{ app.request.get('_route') starts with 'admin_notification_template' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100' }}">
    <i class="bi bi-chat-square-text"></i> Notification Templates
</a>
```

---

## 10. Data Flow Diagram

```
┌─────────────────┐     ┌──────────────────────┐     ┌─────────────────┐
│   Admin UI      │────▶│  API Controller      │────▶│  Database       │
│  (Vue Form)     │     │  (CRUD Operations)   │     │  (Templates)    │
└─────────────────┘     └──────────────────────┘     └─────────────────┘
                                                              │
                                                              ▼
┌─────────────────┐     ┌──────────────────────┐     ┌─────────────────┐
│  Test Run       │────▶│  NotificationService │────▶│TemplateService  │
│  Completes      │     │  (Send Notification) │     │ (Render)        │
└─────────────────┘     └──────────────────────┘     └─────────────────┘
                                   │
                        ┌──────────┴──────────┐
                        ▼                     ▼
               ┌─────────────┐       ┌─────────────┐
               │   Slack     │       │   Email     │
               │   Webhook   │       │   SMTP      │
               └─────────────┘       └─────────────┘
```

---

## 11. Implementation Phases

### Phase 1: Entity & Migration
- Create `NotificationTemplate` entity
- Create repository with helper methods
- Generate and run migration
- Create data fixtures for default templates

### Phase 2: Service Layer
- Create `NotificationTemplateService`
- Implement variable replacement logic
- Add preview generation
- Integrate with existing `NotificationService`

### Phase 3: Admin Controller & API
- Create `NotificationTemplateController`
- Create `NotificationTemplateApiController`
- Implement CRUD endpoints
- Add preview endpoint

### Phase 4: Vue Components
- Create entry point and mount logic
- Build `NotificationTemplateForm.vue`
- Implement `useNotificationTemplateForm.js`
- Add variable toolbar and live preview

### Phase 5: Admin UI
- Create index template (list view)
- Create edit template (Vue island)
- Add navigation link
- Style with existing admin theme

### Phase 6: Testing & Documentation
- Test template rendering
- Test API endpoints
- Update operations documentation

---

## 12. File Checklist

| File | Type | Description |
|------|------|-------------|
| `src/Entity/NotificationTemplate.php` | Entity | Template entity |
| `src/Repository/NotificationTemplateRepository.php` | Repository | DB queries |
| `src/Service/NotificationTemplateService.php` | Service | Template rendering |
| `src/Controller/Admin/NotificationTemplateController.php` | Controller | Admin pages |
| `src/Controller/Api/NotificationTemplateApiController.php` | Controller | API endpoints |
| `templates/admin/notification_template/index.html.twig` | Template | List view |
| `templates/admin/notification_template/edit.html.twig` | Template | Edit page |
| `assets/vue/notification-template-form-app.js` | JS | Vue entry |
| `assets/vue/components/NotificationTemplateForm.vue` | Vue | Form component |
| `assets/vue/composables/useNotificationTemplateForm.js` | JS | Form composable |
| `migrations/VersionXXX.php` | Migration | DB schema |
| `src/DataFixtures/NotificationTemplateFixtures.php` | Fixture | Default data |

---

## 13. Security Considerations

1. **CSRF Protection**: All POST/PUT requests require CSRF token
2. **Role Check**: `#[IsGranted('ROLE_ADMIN')]` on all endpoints
3. **Input Validation**: Validate template syntax before save
4. **XSS Prevention**: Escape variables in email HTML rendering
5. **No Code Execution**: Template variables are simple replacements, not eval
