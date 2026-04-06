<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
    protected $model = AiModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'provider_id' => AiProvider::factory(),
            'name' => fake()->unique()->slug(2),
            'ollama_model_id' => null,
            'display_name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'version' => '1.0',
            'context_window' => 4096,
            'max_output_tokens' => 2048,
            'capabilities' => null,
            'supports_vision' => false,
            'supports_functions' => false,
            'supports_streaming' => true,
            'input_cost_per_1k' => null,
            'output_cost_per_1k' => null,
            'parameter_count' => null,
            'quantization' => null,
            'ollama_digest' => null,
            'is_active' => true,
            'is_default' => false,
            'is_local' => false,
            'update_available' => false,
            'last_updated_at' => null,
        ];
    }
}
