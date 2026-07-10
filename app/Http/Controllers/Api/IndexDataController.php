<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StandupRun;
use App\Models\StandupSchedule;
use App\Models\StandupTemplate;
use App\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IndexDataController extends Controller
{
    public function standups(Request $request, CurrentOrganization $currentOrganization): JsonResponse
    {
        $validated = $this->validateIndex($request, ['run_date', 'status', 'template', 'workspace'], [
            'status' => ['nullable', Rule::in(['scheduled', 'active', 'completed', 'cancelled'])],
            'date' => ['nullable', 'date'],
        ]);
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;

        $query = StandupRun::query()
            ->with('workspace', 'template')
            ->where('organization_id', $organization?->id)
            ->when($workspace, fn (Builder $query) => $query->where('workspace_id', $workspace->id))
            ->when(! $workspace, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas(
                'template', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
            ))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('run_date', $date));

        match ($validated['sort']) {
            'template' => $query->orderBy(
                StandupTemplate::select('name')->whereColumn('standup_templates.id', 'standup_runs.standup_template_id'),
                $validated['direction'],
            ),
            'workspace' => $query->orderBy(
                'workspace_id',
                $validated['direction'],
            ),
            default => $query->orderBy($validated['sort'], $validated['direction']),
        };

        return response()->json($query->orderBy('id', 'desc')->paginate($validated['per_page'])->withQueryString());
    }

    public function templates(Request $request, CurrentOrganization $currentOrganization): JsonResponse
    {
        $validated = $this->validateIndex($request, ['created_at', 'name', 'questions_count'], [
            'workspace_id' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ]);
        $organization = $currentOrganization->fromRequest($request);
        $isOrganizationManager = $organization
            && $currentOrganization->canManageOrganization($organization, $request->user());
        $workspaceIds = $organization
            ? $currentOrganization->manageableWorkspacesForUser($organization, $request->user())->pluck('id')
            : collect();

        $query = StandupTemplate::query()
            ->with('questions', 'workspace')
            ->withCount('questions')
            ->where('organization_id', $organization?->id)
            ->when(! $isOrganizationManager, fn (Builder $query) => $query->whereIn('workspace_id', $workspaceIds))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($validated['workspace_id'] ?? null, fn (Builder $query, int $workspaceId) => $query->where('workspace_id', $workspaceId))
            ->when(array_key_exists('active', $validated), fn (Builder $query) => $query->where('is_active', $validated['active']))
            ->orderBy($validated['sort'], $validated['direction'])
            ->orderBy('id', 'desc');

        return response()->json($query->paginate($validated['per_page'])->withQueryString());
    }

    public function schedules(Request $request, CurrentOrganization $currentOrganization): JsonResponse
    {
        $validated = $this->validateIndex($request, ['created_at', 'name', 'time_of_day'], [
            'workspace_id' => ['nullable', 'integer'],
            'delivery_method' => ['nullable', Rule::in(['email', 'slack_dm', 'slack_channel', 'discord_channel'])],
            'active' => ['nullable', 'boolean'],
        ]);
        $organization = $currentOrganization->fromRequest($request);
        $isOrganizationManager = $organization
            && $currentOrganization->canManageOrganization($organization, $request->user());
        $workspaceIds = $organization
            ? $currentOrganization->manageableWorkspacesForUser($organization, $request->user())->pluck('id')
            : collect();

        $query = StandupSchedule::query()
            ->with('workspace', 'template', 'targetIntegration', 'promptChannel', 'targetChannel')
            ->withCount('participants')
            ->where('organization_id', $organization?->id)
            ->when(! $isOrganizationManager, fn (Builder $query) => $query->whereIn('workspace_id', $workspaceIds))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($validated['workspace_id'] ?? null, fn (Builder $query, int $workspaceId) => $query->where('workspace_id', $workspaceId))
            ->when($validated['delivery_method'] ?? null, fn (Builder $query, string $method) => $query->where('delivery_method', $method))
            ->when(array_key_exists('active', $validated), fn (Builder $query) => $query->where('is_active', $validated['active']))
            ->orderBy($validated['sort'], $validated['direction'])
            ->orderBy('id', 'desc');

        return response()->json($query->paginate($validated['per_page'])->withQueryString());
    }

    public function workspaces(Request $request, CurrentOrganization $currentOrganization): JsonResponse
    {
        $validated = $this->validateIndex($request, ['created_at', 'name', 'users_count', 'templates_count'], [
            'active' => ['nullable', 'boolean'],
        ]);
        $organization = $currentOrganization->fromRequest($request);

        $query = $organization?->workspaces()
            ->withCount('users', 'templates')
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('timezone', 'like', "%{$search}%");
            }))
            ->when(array_key_exists('active', $validated), fn (Builder $query) => $query->where('is_active', $validated['active']))
            ->orderBy($validated['sort'], $validated['direction'])
            ->orderBy('id', 'desc');

        return response()->json($query?->paginate($validated['per_page'])->withQueryString() ?? $this->emptyPage());
    }

    public function teamMembers(Request $request, CurrentOrganization $currentOrganization): JsonResponse
    {
        $validated = $this->validateIndex($request, ['name', 'email', 'created_at', 'role'], [
            'workspace_id' => ['nullable', 'integer'],
            'role' => ['nullable', Rule::in(['admin', 'manager', 'member'])],
        ]);
        $organization = $currentOrganization->fromRequest($request);
        $workspace = $organization && $request->user()
            ? $currentOrganization->currentWorkspaceForUser($organization, $request->user())
            : null;
        $isOrganizationManager = $organization
            && $currentOrganization->canManageOrganization($organization, $request->user());

        $query = $organization?->users()
            ->with(['workspaces' => fn ($query) => $isOrganizationManager
                ? $query
                : $query->whereKey($workspace?->id)])
            ->when(! $isOrganizationManager, fn ($query) => $query->whereHas(
                'workspaces',
                fn ($query) => $query->whereKey($workspace?->id)->where('workspace_user.is_active', true),
            ))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($validated['workspace_id'] ?? null, fn (Builder $query, int $workspaceId) => $query->whereHas(
                'workspaces', fn (Builder $query) => $query->whereKey($workspaceId)
            ))
            ->when($validated['role'] ?? null, fn (Builder $query, string $role) => $query->where('organization_user.role', $role));

        if ($query) {
            $sort = $validated['sort'] === 'role' ? 'organization_user.role' : 'users.'.$validated['sort'];
            $query->orderBy($sort, $validated['direction'])->orderBy('users.id', 'desc');
        }

        return response()->json($query?->paginate($validated['per_page'])->withQueryString() ?? $this->emptyPage());
    }

    /**
     * @param  array<int, string>  $sorts
     * @param  array<string, array<int, mixed>>  $filters
     * @return array<string, mixed>
     */
    private function validateIndex(Request $request, array $sorts, array $filters = []): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'sort' => ['nullable', Rule::in($sorts)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            ...$filters,
        ]) + [
            'per_page' => 10,
            'sort' => $sorts[0],
            'direction' => 'desc',
        ];
    }

    /** @return array<string, int|array<never>|null> */
    private function emptyPage(): array
    {
        return [
            'data' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 10,
            'total' => 0, 'from' => null, 'to' => null,
        ];
    }
}
