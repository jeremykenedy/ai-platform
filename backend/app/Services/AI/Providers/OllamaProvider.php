<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

class OllamaProvider extends AbstractAiProvider
{
    public function __construct()
    {
        parent::__construct(
            (string) config('services.ollama.base_url', 'http://ollama:11434'),
        );

        $this->capabilities = ['chat', 'streaming', 'code', 'embeddings'];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{content: string, tokens_used: int, finish_reason: string}
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ], $options);

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(120)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/api/chat", $payload);

        $response->throw();

        $data = $response->json();

        return [
            'content' => $data['message']['content'] ?? '',
            'tokens_used' => $data['eval_count'] ?? 0,
            'finish_reason' => $data['done_reason'] ?? 'stop',
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ], $options);

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(120)
            ->connectTimeout(10)
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/api/chat", $payload);

        $response->throw();

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if ($line === '') {
                continue;
            }

            $chunk = json_decode($line, true);

            if (! is_array($chunk)) {
                continue;
            }

            $token = $chunk['message']['content'] ?? '';

            if ($token !== '') {
                yield $token;
            }

            if (isset($chunk['done']) && $chunk['done'] === true) {
                yield ['__finish__' => true, 'finish_reason' => $chunk['done_reason'] ?? 'stop'];
                break;
            }
        }
    }

    /**
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        $payload = [
            'model' => $model ?? 'nomic-embed-text:latest',
            'input' => $text,
        ];

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(60)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/api/embed", $payload);

        $response->throw();

        $data = $response->json();

        return $data['embeddings'][0] ?? $data['embedding'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(30)
            ->connectTimeout(10)
            ->get("{$this->baseUrl}/api/tags");

        $response->throw();

        $data = $response->json();
        $models = $data['models'] ?? [];

        return array_map(fn (array $m): array => [
            'name' => $m['name'] ?? '',
            'size' => $m['size'] ?? 0,
            'modified_at' => $m['modified_at'] ?? null,
            'digest' => $m['digest'] ?? null,
        ], $models);
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->connectTimeout(3)->get("{$this->baseUrl}/api/tags");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('[OllamaProvider] isAvailable check failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Pull a model from the Ollama registry, yielding progress updates.
     */
    public function pullModel(string $name): \Generator
    {
        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(600)
            ->connectTimeout(10)
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/api/pull", ['name' => $name, 'stream' => true]);

        $response->throw();

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if ($line === '') {
                continue;
            }

            $chunk = json_decode($line, true);

            if (is_array($chunk)) {
                yield $chunk;
            }
        }
    }

    public function deleteModel(string $name): bool
    {
        try {
            $response = Http::withHeaders($this->buildHeaders())
                ->timeout(30)
                ->connectTimeout(10)
                ->delete("{$this->baseUrl}/api/delete", ['name' => $name]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('[OllamaProvider] deleteModel failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function showModel(string $name): array
    {
        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(30)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/api/show", ['name' => $name]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRunningModels(): array
    {
        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(15)
            ->connectTimeout(10)
            ->get("{$this->baseUrl}/api/ps");

        $response->throw();

        return $response->json()['models'] ?? [];
    }

    public function copyModel(string $source, string $destination): bool
    {
        try {
            $response = Http::withHeaders($this->buildHeaders())
                ->timeout(30)
                ->connectTimeout(10)
                ->post("{$this->baseUrl}/api/copy", [
                    'source' => $source,
                    'destination' => $destination,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('[OllamaProvider] copyModel failed: '.$e->getMessage());

            return false;
        }
    }

    protected function getDefaultTestModel(): string
    {
        return 'llama3.2:latest';
    }

    private function readLine(StreamInterface $body): string
    {
        $line = '';

        while (! $body->eof()) {
            $char = $body->read(1);

            if ($char === "\n") {
                break;
            }

            $line .= $char;
        }

        return trim($line);
    }
}
