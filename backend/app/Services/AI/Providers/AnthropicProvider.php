<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

class AnthropicProvider extends AbstractAiProvider
{
    protected string $apiKey;

    public function __construct()
    {
        parent::__construct('https://api.anthropic.com');

        $this->apiKey = (string) config('services.anthropic.api_key', '');

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
        $payload = array_merge([
            'model'      => $model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages'   => $this->formatMessages($messages),
        ], array_diff_key($options, ['max_tokens' => true]));

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(120)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/v1/messages", $payload);

        $response->throw();

        $data = $response->json();

        $content = '';

        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= $block['text'];
            }
        }

        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;

        return [
            'content'       => $content,
            'tokens_used'   => $inputTokens + $outputTokens,
            'finish_reason' => $data['stop_reason'] ?? 'end_turn',
        ];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        $payload = array_merge([
            'model'      => $model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages'   => $this->formatMessages($messages),
            'stream'     => true,
        ], array_diff_key($options, ['max_tokens' => true, 'stream' => true]));

        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(120)
            ->connectTimeout(10)
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/v1/messages", $payload);

        $response->throw();

        $body = $response->toPsrResponse()->getBody();

        while (!$body->eof()) {
            $line = $this->readLine($body);

            if ($line === '' || !str_starts_with($line, 'data: ')) {
                continue;
            }

            $jsonStr = substr($line, 6);

            if ($jsonStr === '[DONE]') {
                break;
            }

            $event = json_decode($jsonStr, true);

            if (!is_array($event)) {
                continue;
            }

            $type = $event['type'] ?? '';

            if ($type === 'content_block_delta') {
                $deltaText = $event['delta']['text'] ?? '';

                if ($deltaText !== '') {
                    yield $deltaText;
                }
            } elseif ($type === 'message_stop') {
                yield ['__finish__' => true, 'finish_reason' => 'end_turn'];
                break;
            }
        }
    }

    /**
     * Anthropic does not provide an embeddings API.
     *
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        throw new \RuntimeException('AnthropicProvider does not support embeddings.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        return [
            ['id' => 'claude-opus-4-5', 'capabilities' => ['chat', 'vision', 'code', 'reasoning', 'function_calling', 'long_context']],
            ['id' => 'claude-sonnet-4-5', 'capabilities' => ['chat', 'vision', 'code', 'function_calling']],
            ['id' => 'claude-haiku-3-5', 'capabilities' => ['chat', 'vision', 'code', 'function_calling']],
            ['id' => 'claude-opus-4-0', 'capabilities' => ['chat', 'vision', 'code', 'reasoning', 'function_calling', 'long_context']],
            ['id' => 'claude-sonnet-4-0', 'capabilities' => ['chat', 'vision', 'code', 'function_calling']],
            ['id' => 'claude-3-5-sonnet-20241022', 'capabilities' => ['chat', 'vision', 'code', 'function_calling']],
            ['id' => 'claude-3-5-haiku-20241022', 'capabilities' => ['chat', 'vision', 'code', 'function_calling']],
            ['id' => 'claude-3-opus-20240229', 'capabilities' => ['chat', 'vision', 'code', 'function_calling']],
        ];
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
        return [
            'anthropic-version' => '2023-06-01',
            'x-api-key'         => $this->apiKey,
            'content-type'      => 'application/json',
        ];
    }

    protected function getDefaultTestModel(): string
    {
        return 'claude-haiku-3-5';
    }

    /**
     * Convert generic message array to Anthropic format.
     *
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function formatMessages(array $messages): array
    {
        return array_values(array_filter(
            array_map(fn (array $m): array => [
                'role'    => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $m['content'],
            ], $messages),
            fn (array $m): bool => in_array($m['role'], ['user', 'assistant'], true),
        ));
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
