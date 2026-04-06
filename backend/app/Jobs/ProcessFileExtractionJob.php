<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MessageAttachment;
use App\Services\Media\FileExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessFileExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly string $attachmentId,
    ) {
        $this->onQueue('default');
    }

    public function handle(FileExtractionService $fileExtractionService): void
    {
        /** @var MessageAttachment $attachment */
        $attachment = MessageAttachment::findOrFail($this->attachmentId);

        $disk = $attachment->disk ?? 'local';
        $file = Storage::disk($disk)->get($attachment->path);

        if ($file === null || $file === '') {
            Log::warning('[ProcessFileExtractionJob] File not found on disk', [
                'attachment_id' => $this->attachmentId,
                'disk' => $disk,
                'path' => $attachment->path,
            ]);

            $attachment->update(['extraction_status' => 'failed']);

            return;
        }

        $extractionResult = $fileExtractionService->extract($file, (string) $attachment->mime_type);
        $extractedText = (string) ($extractionResult['text'] ?? '');
        $tokenEstimate = (int) $extractionResult['token_estimate'];

        $attachment->update([
            'extracted_text' => $extractedText,
            'extraction_status' => 'complete',
            'token_estimate' => $tokenEstimate,
        ]);

        Log::info('[ProcessFileExtractionJob] Extraction complete', [
            'attachment_id' => $this->attachmentId,
            'mime_type' => $attachment->mime_type,
            'token_estimate' => $tokenEstimate,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessFileExtractionJob] Job failed', [
            'attachment_id' => $this->attachmentId,
            'error' => $exception->getMessage(),
        ]);

        $attachment = MessageAttachment::find($this->attachmentId);

        if ($attachment !== null) {
            $attachment->update(['extraction_status' => 'failed']);
        }
    }
}
