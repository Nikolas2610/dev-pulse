<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\Organization;
use App\Models\StandupTemplate;
use Illuminate\Validation\ValidationException;

class WorkItemProviderService
{
    private const PROVIDERS = ['jira', 'bitbucket'];

    /** @return array<int, string> */
    public function availableForOrganization(Organization $organization): array
    {
        $connected = Integration::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereIn('provider', self::PROVIDERS)
            ->pluck('provider');

        return collect(self::PROVIDERS)
            ->filter(fn (string $provider) => $connected->contains($provider))
            ->values()
            ->all();
    }

    /** @param array<int, mixed>|null $providers
     * @return array<int, string>|null
     */
    public function normalize(?array $providers): ?array
    {
        $normalized = collect($providers ?? [])
            ->intersect(self::PROVIDERS)
            ->unique()
            ->sortBy(fn (string $provider) => array_search($provider, self::PROVIDERS, true))
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }

    /** @param array<int, array<string, mixed>> $questions */
    public function validateQuestions(
        array $questions,
        Organization $organization,
        ?StandupTemplate $template = null,
    ): void {
        $available = $this->availableForOrganization($organization);
        $existing = $template
            ? $template->questions()->get()->keyBy('id')
            : collect();
        $errors = [];

        foreach ($questions as $questionIndex => $question) {
            $existingProviders = ! empty($question['id'])
                ? ($existing->get((int) $question['id'])->work_item_providers ?? [])
                : [];

            foreach (($question['work_item_providers'] ?? []) as $providerIndex => $provider) {
                if (! in_array($provider, $available, true) && ! in_array($provider, $existingProviders, true)) {
                    $errors["questions.{$questionIndex}.work_item_providers.{$providerIndex}"] = __('Connect this integration before enabling it for a question.');
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
