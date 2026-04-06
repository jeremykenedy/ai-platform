<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Database\Eloquent\Collection;

class ExportConversationAction
{
    /**
     * Export a conversation in the requested format.
     *
     * @return array{content: string|array<mixed>, filename: string, mime_type: string}
     */
    public function handle(Conversation $conversation, string $format = 'json'): array
    {
        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('sequence')
            ->with('attachments')
            ->get();

        if ($format === 'markdown') {
            return $this->exportMarkdown($conversation, $messages);
        }

        return $this->exportJson($conversation, $messages);
    }

    /**
     * @param Collection<int, Message> $messages
     *
     * @return array{content: array<mixed>, filename: string, mime_type: string}
     */
    private function exportJson(Conversation $conversation, Collection $messages): array
    {
        $content = [
            'id'         => $conversation->id,
            'title'      => $conversation->title,
            'model_name' => $conversation->model_name,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
            'messages'   => $messages->map(fn (Message $m): array => [
                'id'          => $m->id,
                'role'        => $m->role,
                'content'     => $m->content,
                'sequence'    => $m->sequence,
                'tokens_used' => $m->tokens_used,
                'created_at'  => $m->created_at?->toIso8601String(),
                'attachments' => $m->attachments->map(fn (MessageAttachment $a): array => [
                    'filename'  => $a->filename,
                    'mime_type' => $a->mime_type,
                    'size'      => $a->size,
                ])->all(),
            ])->all(),
        ];

        $slug = $this->titleSlug($conversation->title ?? (string) $conversation->id);

        return [
            'content'   => $content,
            'filename'  => "conversation-{$slug}.json",
            'mime_type' => 'application/json',
        ];
    }

    /**
     * @param Collection<int, Message> $messages
     *
     * @return array{content: string, filename: string, mime_type: string}
     */
    private function exportMarkdown(Conversation $conversation, Collection $messages): array
    {
        $lines = [];
        $title = $conversation->title ?? 'Conversation';
        $lines[] = "# {$title}";
        $lines[] = '';

        if ($conversation->model_name !== null) {
            $lines[] = "_Model: {$conversation->model_name}_";
            $lines[] = '';
        }

        if ($conversation->created_at !== null) {
            $lines[] = "_Exported: {$conversation->created_at->toDateTimeString()}_";
            $lines[] = '';
        }

        $lines[] = '---';
        $lines[] = '';

        foreach ($messages as $message) {
            $roleLabel = $message->role === 'user' ? '## User' : '## Assistant';
            $lines[] = $roleLabel;
            $lines[] = '';
            $lines[] = $message->content;
            $lines[] = '';

            if ($message->attachments->isNotEmpty()) {
                $lines[] = '_Attachments: '.$message->attachments->pluck('filename')->join(', ').'_';
                $lines[] = '';
            }
        }

        $slug = $this->titleSlug($conversation->title ?? (string) $conversation->id);

        return [
            'content'   => implode("\n", $lines),
            'filename'  => "conversation-{$slug}.md",
            'mime_type' => 'text/markdown',
        ];
    }

    private function titleSlug(?string $title): string
    {
        if ($title === null || $title === '') {
            return 'export';
        }

        return substr(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? 'export', 0, 64);
    }
}
