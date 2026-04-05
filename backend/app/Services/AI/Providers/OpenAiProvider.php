<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

class OpenAiProvider extends AbstractAiProvider
{
    protected string $apiKey;

    protected string $organization;

    public function __construct()
    {
        parent::__construct('https://api.openai.com');

        $this->apiKey = (string) config('services.openai.api_key', '');
        $this->organization = (string) config('services.openai.organization', '');

        $this->capabilities = [
            'chat',
            'streaming',
            'vision',
            'code',
            'reasoning',
            'function_calling',
            'file_analysis',
            'long_context',
            'structured_output',
            'embeddings',
            'image_generation',
            'audio_transcription',
        ];
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
        ], $options);

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(120)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/v1/chat/completions", $payload);

        $response->throw();

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
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
            ->post("{$this->baseUrl}/v1/chat/completions", $payload);

        $response->throw();

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if ($line === '' || ! str_starts_with($line, 'data: ')) {
                continue;
            }

            $jsonStr = substr($line, 6);

            if ($jsonStr === '[DONE]') {
                yield ['__finish__' => true, 'finish_reason' => 'stop'];
                break;
            }

            $chunk = json_decode($jsonStr, true);

            if (! is_array($chunk)) {
                continue;
            }

            $delta = $chunk['choices'][0]['delta']['content'] ?? '';

            if ($delta !== '') {
                yield $delta;
            }

            $finishReason = $chunk['choices'][0]['finish_reason'] ?? null;

            if ($finishReason !== null && $finishReason !== '') {
                yield ['__finish__' => true, 'finish_reason' => $finishReason];
            }
        }
    }

    /**
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        $payload = [
            'model' => $model ?? 'text-embedding-3-large',
            'input' => $text,
        ];

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(60)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/v1/embeddings", $payload);

        $response->throw();

        return $response->json()['data'][0]['embedding'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        try {
            $response = Http::withHeaders($this->buildHeaders())
                ->timeout(30)
                ->connectTimeout(10)
                ->get("{$this->baseUrl}/v1/models");

            $response->throw();

            $data = $response->json();
            $chatPrefixes = ['gpt-', 'o1', 'o3', 'o4', 'chatgpt-'];

            return array_values(array_filter(
                array_map(fn (array $m): array => [
                    'id' => $m['id'],
                    'created' => $m['created'] ?? null,
                    'owned_by' => $m['owned_by'] ?? null,
                ], $data['data'] ?? []),
                fn (array $m): bool => collect($chatPrefixes)
                    ->contains(fn (string $prefix): bool => str_starts_with($m['id'], $prefix)),
            ));
        } catch (\Throwable $e) {
            Log::warning('[OpenAiProvider] listModels failed: '.$e->getMessage());

            return [];
        }
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $headers = [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];

        if ($this->organization !== '') {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        return $headers;
    }

    protected function getDefaultTestModel(): string
    {
        return 'gpt-4o-mini';
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
