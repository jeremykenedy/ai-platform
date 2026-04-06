<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    protected $model = AiProvider::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name'                 => fake()->unique()->slug(2),
            'display_name'         => fake()->words(2, true),
            'type'                 => fake()->randomElement(['local', 'remote']),
            'base_url'             => null,
            'is_active'            => true,
            'is_configured'        => true,
            'health_status'        => 'ok',
            'last_health_check_at' => now(),
            'capabilities'         => null,
            'config'               => null,
        ];
    }
}
