# Jira Slack Standup Tickets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add user-scoped Jira ticket selection to mapped standup questions in Slack and web, configurable by Jira status per workspace/project, and render saved tickets with responses.

**Architecture:** Add durable question/status metadata, centralize answer-and-ticket persistence in a shared submission service, and make Slack/web clients submit question-keyed ticket IDs. Jira sync remains the source of ticket ownership and applies each workspace/project status policy.

**Tech Stack:** Laravel, Eloquent, Inertia, React/TypeScript, Slack Block Kit, Jira REST API, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-16-jira-slack-standup-tickets-design.md`

## Global Constraints

- A user sees only Jira issues assigned to their mapped Jira identity in the current workspace.
- Relations are server-derived from `standup_questions.work_item_relation`.
- Null status filters mean every Jira status category except Done.
- Existing templates and historical response items remain usable.
- The same Jira issue may be stored once in each distinct relation.

---

### Task 1: Persist question mappings and Jira status policy

**Files:**
- Create: `database/migrations/2026_09_16_000001_add_jira_standup_ticket_metadata.php`
- Modify: `app/Models/StandupQuestion.php`
- Modify: `app/Models/JiraProject.php`
- Modify: `app/Models/Workspace.php`
- Modify: `app/Models/ExternalWorkItem.php`
- Modify: `app/Models/StandupResponseItem.php`
- Test: `tests/Feature/DevPulse/AtlassianIntegrationTest.php`

**Interfaces:**
- Produces: `StandupQuestion::$work_item_relation`, `JiraProject::$statuses_json`, pivot `included_status_ids`, `ExternalWorkItem::$external_status_id`, and `StandupResponseItem::$standup_question_id`.

- [ ] Write a failing schema/model test asserting the new columns, casts, relationships, default question backfill, and multi-relation unique behavior.
- [ ] Run `php artisan test tests/Feature/DevPulse/AtlassianIntegrationTest.php --filter=metadata` and confirm the missing-column failure.
- [ ] Add the migration, fillable fields, casts, and relationships; replace the old response-item unique index with `(standup_response_id, external_work_item_id, relation)`.
- [ ] Re-run the focused test and confirm it passes.
- [ ] Commit with `feat: add Jira standup ticket metadata`.

### Task 2: Discover statuses and apply workspace-specific Jira sync filters

**Files:**
- Modify: `app/Services/JiraIntegrationService.php`
- Modify: `app/Http/Controllers/AtlassianIntegrationController.php`
- Modify: `app/Models/JiraProject.php`
- Modify: `app/Models/Workspace.php`
- Test: `tests/Feature/DevPulse/AtlassianIntegrationTest.php`

**Interfaces:**
- Produces: `JiraIntegrationService::jqlForWorkspaceProject(JiraProject $project, ?array $includedStatusIds): string` and resource payloads containing `statuses_json` plus per-workspace pivot policy.

- [ ] Write failing HTTP-fake tests proving status discovery stores `{id,name,category_key}`, null filters use `statusCategory != Done`, and explicit filters generate `status IN (...)`.
- [ ] Run the focused Jira sync tests and confirm their assertions fail.
- [ ] Fetch `/rest/api/3/project/{key}/statuses`, normalize duplicate statuses, persist snapshots, load pivot data during issue sync, and persist `external_status_id`.
- [ ] Extend `assignResources` validation to accept `status_filters[project_id]` only for statuses belonging to that integration/project; sync pivot values and queue a refresh.
- [ ] Re-run Jira integration tests and commit with `feat: filter Jira sync by workspace statuses`.

### Task 3: Configure mappings in templates and Jira settings

**Files:**
- Modify: `app/Http/Controllers/TemplateController.php`
- Modify: `app/Http/Controllers/OnboardingController.php`
- Modify: `resources/js/pages/templates/question-editor.tsx`
- Modify: `resources/js/pages/templates/default-questions.ts`
- Modify: `resources/js/pages/templates/edit.tsx`
- Modify: `resources/js/pages/integrations/atlassian.tsx`
- Test: `tests/Feature/DevPulse/DevPulseFoundationTest.php`
- Test: `tests/Feature/DevPulse/AtlassianIntegrationTest.php`

**Interfaces:**
- Consumes: metadata from Task 1 and status resource payloads from Task 2.
- Produces: template payload field `work_item_relation: 'worked_on'|'planned'|'blocker'|'review'|null` and Jira resource form field `status_filters`.

- [ ] Write failing validation tests for allowed relations, duplicate relations, default mappings, and cross-integration status IDs.
- [ ] Run focused template and integration tests to see the validation failures.
- [ ] Add request validation/persistence and UI selectors; map `yesterday`, `today`, and `blocker_detail` defaults.
- [ ] Add Jira per-project status controls with null-as-default behavior to resource assignment forms.
- [ ] Run PHP tests plus `npm.cmd run build`; commit with `feat: configure Jira standup mappings`.

### Task 4: Centralize safe question-keyed work-item submission

**Files:**
- Create: `app/Services/StandupResponseSubmissionService.php`
- Modify: `app/Services/WorkItemSuggestionService.php`
- Modify: `app/Http/Controllers/StandupSubmissionController.php`
- Modify: `app/Models/StandupResponseItem.php`
- Test: `tests/Feature/DevPulse/AtlassianIntegrationTest.php`

**Interfaces:**
- Produces: `StandupResponseSubmissionService::submit(StandupRun $run, User $user, array $answers, array $workItemsByQuestion, string $source, ?string $externalMessageId = null, array $raw = []): StandupResponse`.
- Input format: `work_items[question_id] = [external_work_item_id, ...]`.

- [ ] Write failing tests for user-scoped suggestions, question-keyed persistence, forged IDs, duplicate IDs, hidden dependent questions, and one issue across multiple relations.
- [ ] Run the focused submission tests and confirm failures.
- [ ] Restrict Jira suggestions to mapped assignees and implement transactional shared persistence with server-derived relations and snapshots.
- [ ] Refactor the web controller to validate the new input shape and call the shared service.
- [ ] Re-run tests and commit with `feat: persist Jira tickets by standup question`.

### Task 5: Add Jira ticket multi-selects to Slack

**Files:**
- Modify: `app/Services/SlackIntegrationService.php`
- Modify: `app/Http/Controllers/SlackInteractionController.php`
- Test: `tests/Feature/DevPulse/StandupRuntimeTest.php`
- Test: `tests/Feature/DevPulse/AtlassianIntegrationTest.php`

**Interfaces:**
- Consumes: `WorkItemSuggestionService::forUser()` and `StandupResponseSubmissionService::submit()`.
- Produces: Slack block IDs `work_items_{questionId}` with action ID `selected_items`.

- [ ] Write failing tests asserting mapped questions produce `multi_static_select` options limited to the submitting user's 100 eligible Jira issues.
- [ ] Write a failing Slack submission test that sends `selected_options` and verifies question-linked response-item snapshots.
- [ ] Resolve the Slack identity before opening the modal, build ticket blocks alongside answer blocks, and omit selectors when no tickets are eligible.
- [ ] Parse selected option values, revalidate via the shared service, and keep modal errors attached to the relevant block.
- [ ] Run Slack and Jira feature tests and commit with `feat: collect Jira tickets in Slack standups`.

### Task 6: Add question-centric ticket selection to the web form

**Files:**
- Modify: `resources/js/types/integrations.ts`
- Modify: `resources/js/pages/standup-runs/submit.tsx`
- Test: `resources/js/pages/standup-runs/submit.test.tsx` if the existing frontend test harness supports page tests.
- Test: `tests/Feature/DevPulse/AtlassianIntegrationTest.php`

**Interfaces:**
- Consumes: question `work_item_relation`, `suggestedWorkItems`, and Task 4's input format.

- [ ] Add a failing frontend or response-shape test for per-question multi-select state.
- [ ] Replace the generic relation editor with a ticket picker beneath each mapped visible question, retaining the free-text input.
- [ ] Submit `work_items` keyed by question ID and show field-specific validation errors.
- [ ] Run `npm.cmd run test:frontend` and `npm.cmd run build`; commit with `feat: add Jira selectors to web standups`.

### Task 7: Render Jira tickets in response views and summaries

**Files:**
- Modify: `app/Http/Controllers/TeamStandupController.php`
- Modify: `app/Http/Controllers/StandupRunController.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Services/StandupSummaryService.php`
- Modify: `resources/js/pages/team-standups/index.tsx`
- Modify: `resources/js/pages/standups/show.tsx`
- Test: `tests/Feature/DevPulse/StandupRuntimeTest.php`
- Test: `tests/Feature/DevPulse/AtlassianIntegrationTest.php`

**Interfaces:**
- Produces: serialized answer items `{id,key,title,status,url,relation}` grouped by `standup_question_id`; legacy null-question items are exposed as `linkedWork`.

- [ ] Write failing Inertia and summary tests asserting ticket keys/titles/statuses are returned under the matching answer and legacy items remain visible.
- [ ] Eager-load items, serialize safe snapshots, and group new items by question while preserving a legacy group.
- [ ] Render compact linked-ticket chips/cards below their answers in team and run views.
- [ ] Run focused backend/frontend tests and commit with `feat: show Jira tickets with standup responses`.

### Task 8: Full verification and cleanup

**Files:**
- Modify only files needed to resolve verification findings.

**Interfaces:**
- Consumes: all previous tasks.

- [ ] Run `vendor/bin/pint --dirty --format agent`.
- [ ] Run `php artisan test tests/Feature/DevPulse/AtlassianIntegrationTest.php tests/Feature/DevPulse/StandupRuntimeTest.php tests/Feature/DevPulse/DevPulseFoundationTest.php tests/Feature/DevPulse/ManagerBlockerAlertTest.php`.
- [ ] Run `npm.cmd run test:frontend` and `npm.cmd run build`.
- [ ] Run `git diff --check` and inspect the branch diff for tenant scoping, secrets, and accidental unrelated changes.
- [ ] Commit any formatting-only changes with `style: format Jira standup integration`.
