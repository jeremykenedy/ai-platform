<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StreamInferenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<int, array{role: string, content: string}> */
    public array $context;

    /** @var array<string, mixed> */
    public array $options;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  array<int, array{role: string, content: string}>  $context
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly string $messageId,
        array $context,
        public readonly ?string $modelName = null,
        array $options = [],
    ) {
        $this->context = $context;
        $this->options = $options;
    }

    public function handle(): void
    {
        // Streaming inference implementation dispatched by SendMessageAction / RegenerateMessageAction.
    }

    public function failed(\Throwable $exception): void
    {
        // Failure handling (e.g. mark assistant message as failed) goes here.
    }
}
