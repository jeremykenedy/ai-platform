<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

class GoogleProvider extends AbstractAiProvider
{
    protected string $apiKey;

    public function __construct()
    {
        parent::__construct('https://generativelanguage.googleapis.com');

        $this->apiKey = (string) config('services.google.gemini_api_key', '');

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
        ];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     *
     * @return array{content: string, tokens_used: int, finish_reason: string}
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        $url = "{$this->baseUrl}/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $payload = array_merge([
            'contents' => $this->formatContents($messages),
        ], $options);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(120)
            ->connectTimeout(10)
            ->post($url, $payload);

        $response->throw();

        $data = $response->json();

        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $inputTokens = $data['usageMetadata']['promptTokenCount'] ?? 0;
        $outputTokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;
        $finishReason = strtolower($data['candidates'][0]['finishReason'] ?? 'stop');

        return [
            'content'       => $content,
            'tokens_used'   => $inputTokens + $outputTokens,
            'finish_reason' => $finishReason,
        ];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        $url = "{$this->baseUrl}/v1beta/models/{$model}:streamGenerateContent?key={$this->apiKey}&alt=sse";

        $payload = array_merge([
            'contents' => $this->formatContents($messages),
        ], $options);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(120)
            ->connectTimeout(10)
            ->withOptions(['stream' => true])
            ->post($url, $payload);

        $response->throw();

        $body = $response->toPsrResponse()->getBody();

        while (!$body->eof()) {
            $line = $this->readLine($body);

            if ($line === '' || !str_starts_with($line, 'data: ')) {
                continue;
            }

            $jsonStr = substr($line, 6);
            $chunk = json_decode($jsonStr, true);

            if (!is_array($chunk)) {
                continue;
            }

            $token = $chunk['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if ($token !== '') {
                yield $token;
            }

            $finishReason = $chunk['candidates'][0]['finishReason'] ?? null;

            if ($finishReason !== null && $finishReason !== 'FINISH_REASON_UNSPECIFIED') {
                yield ['__finish__' => true, 'finish_reason' => strtolower($finishReason)];
                break;
            }
        }
    }

    /**
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        $embedModel = $model ?? 'text-embedding-004';
        $url = "{$this->baseUrl}/v1beta/models/{$embedModel}:embedContent?key={$this->apiKey}";

        $payload = [
            'model'   => "models/{$embedModel}",
            'content' => [
                'parts' => [['text' => $text]],
            ],
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->connectTimeout(10)
            ->post($url, $payload);

        $response->throw();

        return $response->json()['embedding']['values'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->connectTimeout(10)
                ->get("{$this->baseUrl}/v1beta/models", ['key' => $this->apiKey]);

            $response->throw();

            $models = $response->json()['models'] ?? [];

            return array_map(fn (array $m): array => [
                'id'                           => $m['name'] ?? '',
                'display_name'                 => $m['displayName'] ?? '',
                'description'                  => $m['description'] ?? '',
                'supported_generation_methods' => $m['supportedGenerationMethods'] ?? [],
            ], $models);
        } catch (\Throwable $e) {
            Log::warning('[GoogleProvider] listModels failed: '.$e->getMessage());

            return [];
        }
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    protected function getDefaultTestModel(): string
    {
        return 'gemini-2.0-flash';
    }

    /**
     * Map generic messages to Gemini's contents format.
     *
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function formatContents(array $messages): array
    {
        return array_map(fn (array $m): array => [
            'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);
    }

    private function readLine(StreamInterface $body): string
    {
        $line = '';

        while (!$body->eof()) {
            $char = $body->read(1);

            if ($char === "\n") {
                break;
            }

            $line .= $char;
        }

        return trim($line);
    }
}
