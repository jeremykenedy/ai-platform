<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class DefaultAiModelsSeeder extends Seeder
{
    public function run(): void
    {
        $providers = $this->createProviders();
        $this->createModels($providers);
    }

    /**
     * @return array<string, AiProvider>
     */
    private function createProviders(): array
    {
        $definitions = [
            'ollama' => [
                'display_name' => 'Ollama',
                'type' => 'local',
                'base_url' => config('services.ollama.base_url', 'http://ollama:11434'),
                'is_active' => true,
            ],
            'anthropic' => [
                'display_name' => 'Anthropic',
                'type' => 'remote',
                'is_active' => false,
            ],
            'openai' => [
                'display_name' => 'OpenAI',
                'type' => 'remote',
                'is_active' => false,
            ],
            'google' => [
                'display_name' => 'Google Gemini',
                'type' => 'remote',
                'is_active' => false,
            ],
            'mistral' => [
                'display_name' => 'Mistral AI',
                'type' => 'remote',
                'is_active' => false,
            ],
            'groq' => [
                'display_name' => 'Groq',
                'type' => 'remote',
                'is_active' => false,
            ],
            'together' => [
                'display_name' => 'Together AI',
                'type' => 'remote',
                'is_active' => false,
            ],
            'openrouter' => [
                'display_name' => 'OpenRouter',
                'type' => 'remote',
                'is_active' => false,
            ],
            'replicate' => [
                'display_name' => 'Replicate',
                'type' => 'remote',
                'is_active' => false,
            ],
            'stability' => [
                'display_name' => 'Stability AI',
                'type' => 'remote',
                'is_active' => false,
            ],
            'elevenlabs' => [
                'display_name' => 'ElevenLabs',
                'type' => 'remote',
                'is_active' => false,
            ],
            'deepgram' => [
                'display_name' => 'Deepgram',
                'type' => 'remote',
                'is_active' => false,
            ],
            'comfyui' => [
                'display_name' => 'ComfyUI',
                'type' => 'local',
                'base_url' => 'http://comfyui:8188',
                'is_active' => false,
            ],
        ];

        $providers = [];

        foreach ($definitions as $name => $attributes) {
            $providers[$name] = AiProvider::firstOrCreate(
                ['name' => $name],
                $attributes,
            );
        }

        return $providers;
    }

    /**
     * @param  array<string, AiProvider>  $providers
     */
    private function createModels(array $providers): void
    {
        $ollamaId = $providers['ollama']->id;

        AiModel::updateOrCreate(
            ['provider_id' => $ollamaId, 'name' => 'llama3.2:latest'],
            [
                'display_name' => 'Llama 3.2 (3B)',
                'capabilities' => ['chat', 'streaming'],
                'parameter_count' => '3B',
                'is_local' => true,
                'is_active' => true,
                'is_default' => true,
            ],
        );

        AiModel::updateOrCreate(
            ['provider_id' => $ollamaId, 'name' => 'qwen2.5:7b'],
            [
                'display_name' => 'Qwen 2.5 (7B)',
                'capabilities' => ['chat', 'streaming', 'code'],
                'parameter_count' => '7B',
                'is_local' => true,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        AiModel::updateOrCreate(
            ['provider_id' => $ollamaId, 'name' => 'mistral:7b'],
            [
                'display_name' => 'Mistral (7B)',
                'capabilities' => ['chat', 'streaming'],
                'parameter_count' => '7B',
                'is_local' => true,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        AiModel::updateOrCreate(
            ['provider_id' => $ollamaId, 'name' => 'nomic-embed-text:latest'],
            [
                'display_name' => 'Nomic Embed Text',
                'capabilities' => ['embeddings'],
                'is_local' => true,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        $anthropicId = $providers['anthropic']->id;
        $claudeCapabilities = ['vision', 'reasoning', 'code', 'chat', 'streaming', 'function_calling', 'structured_output'];

        AiModel::updateOrCreate(
            ['provider_id' => $anthropicId, 'name' => 'claude-opus-4-5'],
            [
                'display_name' => 'Claude Opus 4.5',
                'capabilities' => $claudeCapabilities,
                'is_local' => false,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        AiModel::updateOrCreate(
            ['provider_id' => $anthropicId, 'name' => 'claude-sonnet-4-5'],
            [
                'display_name' => 'Claude Sonnet 4.5',
                'capabilities' => $claudeCapabilities,
                'is_local' => false,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        AiModel::updateOrCreate(
            ['provider_id' => $anthropicId, 'name' => 'claude-haiku-4-5'],
            [
                'display_name' => 'Claude Haiku 4.5',
                'capabilities' => $claudeCapabilities,
                'is_local' => false,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        $openaiId = $providers['openai']->id;

        AiModel::updateOrCreate(
            ['provider_id' => $openaiId, 'name' => 'gpt-4o'],
            [
                'display_name' => 'GPT-4o',
                'capabilities' => ['vision', 'reasoning', 'code', 'chat', 'streaming', 'function_calling', 'structured_output'],
                'is_local' => false,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        AiModel::updateOrCreate(
            ['provider_id' => $openaiId, 'name' => 'gpt-4o-mini'],
            [
                'display_name' => 'GPT-4o Mini',
                'capabilities' => ['chat', 'streaming', 'code'],
                'is_local' => false,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        $googleId = $providers['google']->id;

        AiModel::updateOrCreate(
            ['provider_id' => $googleId, 'name' => 'gemini-2.0-flash'],
            [
                'display_name' => 'Gemini 2.0 Flash',
                'capabilities' => ['chat', 'streaming', 'vision', 'code'],
                'is_local' => false,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        AiModel::updateOrCreate(
            ['provider_id' => $googleId, 'name' => 'gemini-2.0-pro'],
            [
                'display_name' => 'Gemini 2.0 Pro',
                'capabilities' => ['chat', 'streaming', 'vision', 'code', 'reasoning'],
                'is_local' => false,
                'is_active' => true,
                'is_default' => false,
            ],
        );

        $groqId = $providers['groq']->id;

        AiModel::updateOrCreate(
            ['provider_id' => $groqId, 'name' => 'llama-3.3-70b-versatile'],
            [
                'display_name' => 'Llama 3.3 70B Versatile',
                'capabilities' => ['chat', 'streaming', 'code'],
                'is_local' => false,
                'is_active' => true,
                'is_default' => false,
            ],
        );
    }
}
