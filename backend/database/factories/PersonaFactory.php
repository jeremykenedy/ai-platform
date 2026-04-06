<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'name'           => fake()->words(3, true),
            'description'    => fake()->sentence(),
            'system_prompt'  => fake()->paragraph(),
            'model_name'     => null,
            'temperature'    => 0.70,
            'top_p'          => 0.90,
            'top_k'          => 40,
            'repeat_penalty' => 1.10,
        ];
    }
}
