<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\StandupQuestion;
use App\Models\StandupTemplate;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);

        return Inertia::render('templates/index', [
            'indexEndpoint' => route('api.templates.index'),
            'workspaces' => $organization
                ? $currentOrganization->manageableWorkspacesForUser($organization, $request->user())->map->only(['id', 'name'])->values()
                : [],
            'canManageTemplates' => $organization
                ? $currentOrganization->canManageCurrentWorkspace($organization, $request->user())
                : false,
        ]);
    }

    public function create(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization !== null, 403);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);

        return Inertia::render('templates/create', [
            'workspaces' => $currentOrganization->manageableWorkspacesForUser($organization, $request->user())->map->only(['id', 'name'])->values(),
            'canManageTemplates' => true,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization !== null, 403);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'workspace_id' => ['nullable', 'integer'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.question_key' => ['nullable', 'string', 'max:255'],
            'questions.*.answer_type' => ['required', 'string', 'in:text,textarea,boolean,select,multi_select,number'],
            'questions.*.options_json' => ['nullable', 'array'],
            'questions.*.depends_on_question_id' => ['nullable', 'integer'],
            'questions.*.depends_on_question_index' => ['nullable', 'integer', 'min:0'],
            'questions.*.depends_on_operator' => ['nullable', 'string', 'in:equals'],
            'questions.*.depends_on_value' => ['nullable', 'string', 'max:255'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);
        $validatedQuestions = $this->normalizeQuestionOptions(is_array($validated['questions'] ?? null) ? $validated['questions'] : []);
        $this->authorizeWorkspaceSelection(
            $validated['workspace_id'] ?? null,
            $organization,
            $request,
            $currentOrganization,
        );

        $template = StandupTemplate::create([
            'organization_id' => $organization->id,
            'workspace_id' => $validated['workspace_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'created_by_user_id' => $request->user()->id,
        ]);

        $savedQuestions = new Collection;

        foreach ($validatedQuestions as $question) {
            $savedQuestions->push($template->questions()->create([
                'question_text' => $question['question_text'],
                'question_key' => $question['question_key'] ?? null,
                'answer_type' => $question['answer_type'],
                'options_json' => $question['options_json'] ?? null,
                'is_required' => (bool) ($question['is_required'] ?? true),
                'sort_order' => $question['sort_order'],
            ]));
        }

        $this->syncQuestionDependencies($validatedQuestions, $savedQuestions);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return to_route('templates.index');
    }

    public function edit(Request $request, StandupTemplate $template, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $template->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeTemplateWorkspace($template, $organization, $request, $currentOrganization);

        return Inertia::render('templates/edit', [
            'template' => $template->load('questions'),
            'workspaces' => $currentOrganization->manageableWorkspacesForUser($organization, $request->user())->map->only(['id', 'name'])->values(),
            'canManageTemplates' => true,
        ]);
    }

    public function update(Request $request, StandupTemplate $template, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $template->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeTemplateWorkspace($template, $organization, $request, $currentOrganization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.question_key' => ['nullable', 'string', 'max:255'],
            'questions.*.answer_type' => ['required', 'string', 'in:text,textarea,boolean,select,multi_select,number'],
            'questions.*.options_json' => ['nullable', 'array'],
            'questions.*.depends_on_question_id' => ['nullable', 'integer'],
            'questions.*.depends_on_question_index' => ['nullable', 'integer', 'min:0'],
            'questions.*.depends_on_operator' => ['nullable', 'string', 'in:equals'],
            'questions.*.depends_on_value' => ['nullable', 'string', 'max:255'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);
        $validatedQuestions = $this->normalizeQuestionOptions(is_array($validated['questions'] ?? null) ? $validated['questions'] : []);

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        $keptQuestionIds = [];
        $savedQuestions = new Collection;

        foreach ($validatedQuestions as $question) {
            $standupQuestion = StandupQuestion::updateOrCreate(
                [
                    'id' => $question['id'] ?? null,
                    'standup_template_id' => $template->id,
                ],
                [
                    'question_text' => $question['question_text'],
                    'question_key' => $question['question_key'] ?? null,
                    'answer_type' => $question['answer_type'],
                    'options_json' => $question['options_json'] ?? null,
                    'is_required' => (bool) ($question['is_required'] ?? true),
                    'sort_order' => $question['sort_order'],
                ],
            );

            $keptQuestionIds[] = $standupQuestion->id;
            $savedQuestions->push($standupQuestion);
        }

        $this->syncQuestionDependencies($validatedQuestions, $savedQuestions);

        $template->questions()
            ->whereNotIn('id', $keptQuestionIds)
            ->delete();

        return to_route('templates.index');
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestionOptions(array $questions): array
    {
        $errors = [];

        foreach ($questions as $index => $question) {
            $rawOptions = $question['options_json'] ?? [];
            $options = collect(is_array($rawOptions) ? $rawOptions : [])
                ->map(fn ($option) => trim((string) $option))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (in_array($question['answer_type'] ?? null, ['select', 'multi_select'], true) && $options === []) {
                $errors["questions.{$index}.options_json"] = __('Add at least one answer option.');
            }

            $questions[$index]['options_json'] = $options === [] ? null : $options;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $questions;
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     * @param  Collection<int, StandupQuestion>  $savedQuestions
     */
    private function syncQuestionDependencies(array $questions, Collection $savedQuestions): void
    {
        foreach ($questions as $index => $question) {
            $savedQuestion = $savedQuestions->get($index);

            if (! $savedQuestion instanceof StandupQuestion) {
                continue;
            }

            $dependsOnQuestionId = null;
            $dependsOnIndex = $question['depends_on_question_index'] ?? null;

            if (is_numeric($dependsOnIndex) && $savedQuestions->has((int) $dependsOnIndex)) {
                $dependsOnQuestionId = $savedQuestions->get((int) $dependsOnIndex)?->id;
            } elseif (! empty($question['depends_on_question_id'])) {
                $dependsOnQuestionId = (int) $question['depends_on_question_id'];
            }

            if ($dependsOnQuestionId === $savedQuestion->id || blank($question['depends_on_value'] ?? null)) {
                $dependsOnQuestionId = null;
            }

            $savedQuestion->forceFill([
                'depends_on_question_id' => $dependsOnQuestionId,
                'depends_on_operator' => $dependsOnQuestionId ? ($question['depends_on_operator'] ?? 'equals') : null,
                'depends_on_value' => $dependsOnQuestionId ? ($question['depends_on_value'] ?? null) : null,
            ])->save();
        }
    }

    public function destroy(Request $request, StandupTemplate $template, CurrentOrganization $currentOrganization): RedirectResponse
    {
        $organization = $currentOrganization->fromRequest($request);
        abort_unless($organization && $template->organization_id === $organization->id, 404);
        abort_unless($currentOrganization->canManageCurrentWorkspace($organization, $request->user()), 403);
        $this->authorizeTemplateWorkspace($template, $organization, $request, $currentOrganization);

        $template->delete();

        return to_route('templates.index');
    }

    private function authorizeWorkspaceSelection(
        ?int $workspaceId,
        Organization $organization,
        Request $request,
        CurrentOrganization $currentOrganization,
    ): void {
        if ($workspaceId === null) {
            abort_unless($currentOrganization->canManageOrganization($organization, $request->user()), 403);

            return;
        }

        abort_unless(
            $currentOrganization->manageableWorkspacesForUser($organization, $request->user())->contains('id', $workspaceId),
            404,
        );
    }

    private function authorizeTemplateWorkspace(
        StandupTemplate $template,
        Organization $organization,
        Request $request,
        CurrentOrganization $currentOrganization,
    ): void {
        $this->authorizeWorkspaceSelection(
            $template->workspace_id,
            $organization,
            $request,
            $currentOrganization,
        );
    }
}
