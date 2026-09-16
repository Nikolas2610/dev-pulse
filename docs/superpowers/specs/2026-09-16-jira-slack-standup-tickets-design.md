# Jira tickets in Slack standups — design

**Date:** 2026-09-16  
**Branch:** `codex/jira-slack-standup-tickets`  
**Base:** `development`

## Goal

Make Jira work items a first-class part of daily standups across Slack and the web app. Each participant should see only Jira tickets assigned to their mapped Jira identity, select tickets per standup question, add free-form context, and see those selections in response views. Workspace admins should control which Jira project statuses are eligible.

The initial mapped relations are:

- yesterday's work: `worked_on`
- today's planned work: `planned`
- blocker details: `blocker`
- optional future review-related questions: `review`

## Non-goals

- This change does not let managers manually assign tickets to individual DevPulse users. Ownership continues to come from Jira's assignee plus the Jira-to-DevPulse identity mapping.
- This change does not modify Jira tickets, statuses, or assignees.
- This change does not expose tickets across organizations or workspaces.
- This change does not replace free-text standup answers; ticket selection augments them.

## User experience

### Template questions

The question editor gains an optional **Jira ticket selector** field with the values `None`, `Worked on`, `Planned`, `Blocker`, and `Review`. A template may use each relation at most once so response summaries remain unambiguous.

Default daily standup questions are mapped as follows:

| Question key | Jira relation |
| --- | --- |
| `yesterday` | `worked_on` |
| `today` | `planned` |
| `blocker_detail` | `blocker` |
| `blocker` | none; this remains the Yes/No gate |

Existing custom questions default to no Jira selector and continue to behave exactly as they do now. Existing default-style templates are backfilled by `question_key` where a relation is still empty.

### Slack modal

The Slack user is resolved to a DevPulse user through the active Slack integration. For each visible mapped question, the modal renders:

1. the existing answer input; and
2. a `multi_static_select` containing eligible Jira tickets for that DevPulse user and workspace.

Ticket labels use `KEY · title`, truncated to Slack's limits. The option description shows project and status when space permits. The selected value is the internal `external_work_items.id`, never a Jira URL or unscoped external key.

The Slack API supports at most 100 static options, matching the current suggestion service limit. Suggestions remain ordered with blocked and recently updated items first. If no linked Jira identity or no eligible tickets exist, the text answer remains available and the ticket selector is omitted with a short contextual note.

Dependent-question behavior remains server-enforced. The existing blocker Yes/No question controls visibility/acceptance of the blocker-detail answer and ticket selection.

### Web submission

The web form adopts the same question-centric layout: a mapped question shows its existing text/control input and the Jira multi-select directly beneath it. The generic relation picker is removed from the normal UI. The server derives the relation from the question rather than trusting a client-provided relation.

### Response views

Team standups, run details, dashboard response details, and generated summaries eager-load response items. Selected tickets appear under the question they belong to with key, title, status, and a safe Jira link. Legacy items without a question reference appear in a final **Linked work** group based on their stored relation.

The same Jira ticket may appear in multiple question categories in one response, for example both `worked_on` and `planned`.

## Data model

An additive migration introduces:

### `standup_questions`

- nullable string `work_item_relation`
- application validation restricts values to `worked_on`, `planned`, `blocker`, or `review`
- a template-level validation rule prevents duplicate non-null relations

### `jira_projects`

- nullable JSON `statuses_json`
- each status snapshot contains `id`, `name`, and `category_key`
- statuses are refreshed from `GET /rest/api/3/project/{projectIdOrKey}/statuses`

### `workspace_jira_project`

- nullable JSON `included_status_ids`
- `null` means the safe default: every status whose category is not `done`
- a non-empty array means the exact Jira status IDs selected by the workspace admin
- an explicitly empty array is rejected so an accidental empty selection cannot silently remove every ticket

The pivot data is exposed through `withPivot('included_status_ids')` and cast at the model/service boundary.

### `external_work_items`

- nullable string `external_status_id`
- Jira sync persists both the stable status ID and the current display name

### `standup_response_items`

- nullable foreign key `standup_question_id`, null on question deletion
- the old unique constraint on `(standup_response_id, external_work_item_id)` is replaced with `(standup_response_id, external_work_item_id, relation)`
- the relation remains snapshotted for durable reporting and legacy rendering

The relation-based unique key deliberately permits the same ticket in multiple categories while preventing accidental duplicates within one category. New submissions also store `standup_question_id` for exact placement in the response UI.

## Jira status discovery and synchronization

Project sync fetches and stores each project's available statuses. A status fetch failure preserves the last known status snapshot, records the integration sync error, and does not erase the admin's selection.

Issue sync becomes workspace-aware because two workspaces can attach the same Jira project with different status filters:

- default filter: `project = "KEY" AND statusCategory != Done AND assignee IS NOT EMPTY`
- explicit filter: `project = "KEY" AND status IN (<escaped status IDs>) AND assignee IS NOT EMPTY`

Only status IDs returned for that Jira project may be saved. Removed Jira statuses are displayed as unavailable in settings until the admin saves a replacement; they are never silently substituted. The sync continues to close previously open local items that are no longer returned for a workspace/project filter.

Jira suggestions are narrowed to Jira issues assigned to the mapped Jira integration user. Bitbucket review suggestions may continue to use the existing author/reviewer/repository rules, but they are not shown in Jira-only selectors.

## Admin settings

The Jira integration page keeps project-to-workspace assignment and adds a status filter for every assigned project:

- **All active statuses except Done** (default)
- **Selected statuses** using the fetched Jira status list
- **Reset to default**

Statuses are grouped or labelled by Jira category (`To Do`, `In Progress`, `Done`) while preserving each project's custom status names. Saving a project assignment or filter queues a Jira sync so suggestions converge without requiring a manual full reconnect.

## Submission and validation flow

Both web and Slack submissions use a shared application service so they cannot drift:

1. Load the run, visible template questions, workspace, and submitting DevPulse user.
2. Resolve eligible Jira suggestions with `WorkItemSuggestionService`.
3. Validate every submitted question ID belongs to the run's template.
4. Validate every selected work-item ID is in the submitting user's eligible suggestion set.
5. Derive the stored relation from `standup_questions.work_item_relation`.
6. In one database transaction, replace answers and response items, snapshot ticket metadata, record the activity event, and evaluate run completion.
7. Run the existing submission finalizer after commit.

Forged IDs, items belonging to another user/workspace/organization, hidden dependent questions, duplicate selections, and mismatched client relations are rejected or ignored according to the existing question-flow rules. Slack retry idempotency continues to use the modal view ID and the response's completion state.

## Multi-tenancy and security

- Jira projects and users are always reached through the current organization integration.
- Eligible tickets must match the response workspace and the submitting user's mapped Jira account.
- Saved status IDs must belong to the selected Jira project and organization integration.
- The server never accepts an organization, workspace, Jira relation, URL, title, or status snapshot directly from the client.
- OAuth credentials and tokens remain server-only and are not added to Inertia props or Slack metadata.

## Backward compatibility and rollout

- All migrations are additive except for replacing the overly restrictive response-item unique index.
- Existing templates without a mapping still collect text answers normally.
- Existing response items retain their relation and render in the legacy **Linked work** group.
- Default templates and matching existing standard questions are mapped by `question_key` during migration, without overwriting an explicit future mapping.
- Existing status behavior remains the default because a null filter preserves `statusCategory != Done`.

## Testing strategy

Feature and unit coverage will include:

- migration/model casts and relationships;
- default-question mapping and duplicate-relation validation;
- Jira project-status discovery and preservation on API failure;
- default and explicit workspace-specific JQL generation;
- Jira suggestion scoping to the mapped assignee, workspace, project, and status filter;
- Slack modal payloads for mapped/unmapped users, dependent questions, zero tickets, and the 100-option limit;
- Slack submission persisting text answers and ticket snapshots transactionally;
- web submission parity through the shared submission service;
- rejection of forged or cross-tenant work-item IDs;
- the same ticket saved under multiple relations without duplicate rows within one relation;
- response serializers and UI rendering items under the correct questions;
- legacy response-item rendering;
- regression coverage for Slack retries, skip flow, run completion, and blocker alerts.

## Acceptance criteria

1. A mapped user sees only their own eligible Jira tickets in Slack and web standup forms.
2. Yesterday, today, and blocker-detail questions support multi-select Jira tickets plus free text.
3. An admin can select eligible Jira statuses per workspace/project using statuses fetched from Jira.
4. The default filter remains all non-Done statuses.
5. A ticket can be selected in more than one standup category.
6. Saved Jira selections appear beside the corresponding answers in response views and summaries.
7. Web and Slack enforce identical authorization, visibility, and persistence rules.
8. Existing templates and historical responses remain usable.
