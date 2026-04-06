<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\UserSetting;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GoogleProvider;
use App\Services\AI\Providers\GroqProvider;
use App\Services\AI\Providers\MistralProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAiProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Services\AI\Providers\ReplicateProvider;
use App\Services\AI\Providers\TogetherProvider;
use Illuminate\Support\Facades\Log;

class ModelRouterService
{
    /** @var array<string, AiProviderInterface> */
    private array $providerCache = [];

    /** @var array<string, class-string<AiProviderInterface>> */
    private array $providerMap = [
        'ollama' => OllamaProvider::class,
        'anthropic' => AnthropicProvider::class,
        'openai' => OpenAiProvider::class,
        'gemini' => GoogleProvider::class,
        'mistral' => MistralProvider::class,
        'groq' => GroqProvider::class,
        'together' => TogetherProvider::class,
        'openrouter' => OpenRouterProvider::class,
        'replicate' => ReplicateProvider::class,
    ];

    /**
     * Route to the optimal provider and model for the given request.
     *
     * @param  array<string, mixed>  $context
     * @return array{provider: AiProviderInterface, model: string}
     */
    public function route(string $requestedModel, array $context = []): array
    {
        if ($requestedModel === 'auto') {
            return $this->autoRoute($context);
        }

        return $this->routeToSpecificModel($requestedModel);
    }

    /**
     * Resolve a provider instance by name string.
     */
    public function resolveProvider(string $providerName): AiProviderInterface
    {
        $providerName = strtolower($providerName);

        if (isset($this->providerCache[$providerName])) {
            return $this->providerCache[$providerName];
        }

        if (! isset($this->providerMap[$providerName])) {
            throw new \InvalidArgumentException("Unknown provider: {$providerName}");
        }

        $class = $this->providerMap[$providerName];

        if (! class_exists($class)) {
            throw new \RuntimeException("Provider class not found: {$class}");
        }

        $provider = app($class);

        $this->providerCache[$providerName] = $provider;

        return $provider;
    }

    /**
     * Return names of all currently available providers.
     *
     * @return string[]
     */
    public function getAvailableProviders(): array
    {
        $available = [];

        foreach (array_keys($this->providerMap) as $name) {
            try {
                $provider = $this->resolveProvider($name);

                if ($provider->isAvailable()) {
                    $available[] = $name;
                }
            } catch (\Throwable $e) {
                Log::debug("[ModelRouterService] Provider {$name} unavailable: ".$e->getMessage());
            }
        }

        return $available;
    }

    /**
     * Get the default model name for the given user, falling back to config.
     */
    public function getDefaultModel(?int $userId = null): string
    {
        if ($userId !== null) {
            $setting = UserSetting::where('user_id', $userId)
                ->with('defaultModel')
                ->first();

            if ($setting?->defaultModel?->name) {
                return $setting->defaultModel->name;
            }
        }

        return (string) config('ai.default_local_model', 'llama3.2:latest');
    }

    /**
     * Auto-route based on context signals.
     *
     * @param  array<string, mixed>  $context
     * @return array{provider: AiProviderInterface, model: string}
     */
    private function autoRoute(array $context): array
    {
        $userId = isset($context['user_id']) ? (int) $context['user_id'] : null;
        $content = (string) ($context['content'] ?? '');
        $hasImage = (bool) ($context['has_image'] ?? false);

        if ($hasImage) {
            return $this->routeToVisionModel();
        }

        if ($this->looksLikeCode($content)) {
            return $this->routeToCodeModel();
        }

        if ($this->looksLikeReasoning($content)) {
            return $this->routeToReasoningModel();
        }

        $defaultModel = $this->getDefaultModel($userId);

        return $this->routeToSpecificModel($defaultModel);
    }

    /**
     * Route to the best available vision model.
     *
     * @return array{provider: AiProviderInterface, model: string}
     */
    private function routeToVisionModel(): array
    {
        $visionModels = AiModel::active()
            ->withVision()
            ->with('provider')
            ->orderBy('is_local', 'desc')
            ->get();

        foreach ($visionModels as $model) {
            if ($model->provider === null) {
                continue;
            }

            try {
                $provider = $this->resolveProvider($model->provider->name);

                if ($provider->isAvailable() && $provider->supportsCapability('vision')) {
                    return ['provider' => $provider, 'model' => $model->name];
                }
            } catch (\Throwable $e) {
                Log::debug('[ModelRouterService] Vision model unavailable: '.$e->getMessage());
            }
        }

        return $this->routeToFallback();
    }

    /**
     * Route to the best available code model.
     *
     * @return array{provider: AiProviderInterface, model: string}
     */
    private function routeToCodeModel(): array
    {
        $codeModels = AiModel::active()
            ->with('provider')
            ->where(function ($query): void {
                $query->where('name', 'like', '%coder%')
                    ->orWhere('name', 'like', '%code%')
                    ->orWhere('name', 'like', '%starcoder%')
                    ->orWhereJsonContains('capabilities', 'code');
            })
            ->orderBy('is_local', 'desc')
            ->get();

        foreach ($codeModels as $model) {
            if ($model->provider === null) {
                continue;
            }

            try {
                $provider = $this->resolveProvider($model->provider->name);

                if ($provider->isAvailable()) {
                    return ['provider' => $provider, 'model' => $model->name];
                }
            } catch (\Throwable $e) {
                Log::debug('[ModelRouterService] Code model unavailable: '.$e->getMessage());
            }
        }

        return $this->routeToFallback();
    }

    /**
     * Route to the best available reasoning model.
     *
     * @return array{provider: AiProviderInterface, model: string}
     */
    private function routeToReasoningModel(): array
    {
        $reasoningModels = AiModel::active()
            ->with('provider')
            ->where(function ($query): void {
                $query->where('name', 'like', '%deepseek-r1%')
                    ->orWhere('name', 'like', '%qwq%')
                    ->orWhere('name', 'like', '%o1%')
                    ->orWhere('name', 'like', '%o3%')
                    ->orWhereJsonContains('capabilities', 'reasoning');
            })
            ->orderBy('is_local', 'desc')
            ->get();

        foreach ($reasoningModels as $model) {
            if ($model->provider === null) {
                continue;
            }

            try {
                $provider = $this->resolveProvider($model->provider->name);

                if ($provider->isAvailable()) {
                    return ['provider' => $provider, 'model' => $model->name];
                }
            } catch (\Throwable $e) {
                Log::debug('[ModelRouterService] Reasoning model unavailable: '.$e->getMessage());
            }
        }

        return $this->routeToFallback();
    }

    /**
     * Route to a specific model by name, with OpenRouter fallback.
     *
     * @return array{provider: AiProviderInterface, model: string}
     */
    private function routeToSpecificModel(string $modelName): array
    {
        $aiModel = AiModel::active()
            ->where('name', $modelName)
            ->with('provider')
            ->first();

        if ($aiModel?->provider !== null) {
            try {
                $provider = $this->resolveProvider($aiModel->provider->name);

                if ($provider->isAvailable()) {
                    return ['provider' => $provider, 'model' => $modelName];
                }

                Log::info("[ModelRouterService] Provider {$aiModel->provider->name} unavailable for model {$modelName}, trying OpenRouter fallback.");
            } catch (\Throwable $e) {
                Log::warning("[ModelRouterService] Failed to resolve provider for {$modelName}: ".$e->getMessage());
            }
        }

        // Attempt OpenRouter fallback for unknown or unavailable models
        try {
            $openRouter = $this->resolveProvider('openrouter');

            if ($openRouter->isAvailable()) {
                return ['provider' => $openRouter, 'model' => $modelName];
            }
        } catch (\Throwable $e) {
            Log::warning('[ModelRouterService] OpenRouter fallback also unavailable: '.$e->getMessage());
        }

        return $this->routeToFallback();
    }

    /**
     * Priority fallback: Ollama -> Groq -> any available provider.
     *
     * @return array{provider: AiProviderInterface, model: string}
     */
    private function routeToFallback(): array
    {
        $priorityOrder = ['ollama', 'groq', 'openrouter', 'anthropic', 'openai', 'gemini', 'mistral'];

        foreach ($priorityOrder as $providerName) {
            try {
                $provider = $this->resolveProvider($providerName);

                if (! $provider->isAvailable()) {
                    continue;
                }

                $model = $this->getDefaultModelForProvider($providerName);

                return ['provider' => $provider, 'model' => $model];
            } catch (\Throwable $e) {
                Log::debug("[ModelRouterService] Fallback provider {$providerName} unavailable: ".$e->getMessage());
            }
        }

        throw new \RuntimeException('No AI providers are available. Please check your configuration.');
    }

    private function getDefaultModelForProvider(string $providerName): string
    {
        $defaults = [
            'ollama' => (string) config('ai.default_local_model', 'llama3.2:latest'),
            'groq' => 'llama-3.3-70b-versatile',
            'openrouter' => 'meta-llama/llama-3.3-70b-instruct',
            'anthropic' => 'claude-sonnet-4-5',
            'openai' => 'gpt-4o',
            'gemini' => 'gemini-2.0-flash',
            'mistral' => 'mistral-large-latest',
        ];

        return $defaults[$providerName] ?? (string) config('ai.default_local_model', 'llama3.2:latest');
    }

    private function looksLikeCode(string $content): bool
    {
        if (str_contains($content, '```')) {
            return true;
        }

        $codePatterns = [
            '/\bfunction\s+\w+\s*\(/',
            '/\bclass\s+\w+/',
            '/\bimport\s+[\w{]/i',
            '/\brequire\s*\(/i',
            '/\bconst\s+\w+\s*=/',
            '/\bdef\s+\w+\s*\(/i',
            '/\b(if|for|while|foreach)\s*\(/i',
            '/\$\w+\s*=/',
            '/=>/i',
        ];

        foreach ($codePatterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeReasoning(string $content): bool
    {
        $keywords = ['think', 'reason', 'analyze', 'analyse', 'step by step', 'explain why', 'prove', 'deduce', 'infer'];
        $lower = strtolower($content);

        foreach ($keywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
