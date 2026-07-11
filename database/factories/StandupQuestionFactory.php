<?php

namespace Database\Factories;

use App\Models\StandupQuestion;
use App\Models\StandupTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StandupQuestion> */
class StandupQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'standup_template_id' => StandupTemplate::factory(),
            'question_text' => fake()->sentence(),
            'question_key' => fake()->slug(2),
            'answer_type' => 'textarea',
            'options_json' => null,
            'is_required' => true,
            'sort_order' => 1,
        ];
    }
}
