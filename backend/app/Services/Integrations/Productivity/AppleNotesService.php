<?php

declare(strict_types=1);

namespace App\Services\Integrations\Productivity;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Process;

class AppleNotesService extends AbstractIntegrationService
{
    protected string $integrationName = 'apple_notes';

    public function isConnected(User $user): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    public function getAuthUrl(User $user): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function handleCallback(User $user, array $params): void {}

    public function testConnection(User $user): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return false;
        }

        $script = 'tell application "Notes" to return name of first account';
        $result = Process::run(['osascript', '-e', $script]);

        return $result->successful();
    }

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'list_notes',
                'description' => 'List notes from Apple Notes, optionally filtered by folder.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'folder' => ['type' => 'string', 'description' => 'Folder name to list notes from. Omit for all notes.'],
                    ],
                ],
            ],
            [
                'name' => 'get_note',
                'description' => 'Get the full content of an Apple Note by name.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['noteId'],
                    'properties' => [
                        'noteId' => ['type' => 'string', 'description' => 'Name of the note to retrieve.'],
                    ],
                ],
            ],
            [
                'name' => 'add_note',
                'description' => 'Create a new note in Apple Notes.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['title', 'body'],
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Title for the new note.'],
                        'body' => ['type' => 'string', 'description' => 'Body text of the note.'],
                        'folder' => ['type' => 'string', 'description' => 'Folder to create the note in (defaults to Notes).'],
                    ],
                ],
            ],
            [
                'name' => 'update_note',
                'description' => 'Append text to an existing Apple Note.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['noteId', 'body'],
                    'properties' => [
                        'noteId' => ['type' => 'string', 'description' => 'Name of the note to update.'],
                        'body' => ['type' => 'string', 'description' => 'Text to append to the note body.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            throw new \RuntimeException('Apple Notes is only available on macOS.');
        }

        return match ($toolName) {
            'list_notes' => $this->listNotes($params),
            'get_note' => $this->getNote($params),
            'add_note' => $this->addNote($params),
            'update_note' => $this->updateNote($params),
            default => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listNotes(array $params): array
    {
        $folder = $params['folder'] ?? null;

        if ($folder !== null) {
            $folderEscaped = $this->escapeAppleScriptString((string) $folder);
            $script = <<<SCRIPT
                tell application "Notes"
                    set theFolder to first folder whose name is {$folderEscaped}
                    set noteNames to {}
                    repeat with n in notes of theFolder
                        set end of noteNames to name of n
                    end repeat
                    return noteNames
                end tell
                SCRIPT;
        } else {
            $script = <<<'SCRIPT'
                tell application "Notes"
                    set noteNames to {}
                    repeat with n in every note
                        set end of noteNames to name of n
                    end repeat
                    return noteNames
                end tell
                SCRIPT;
        }

        $result = Process::run(['osascript', '-e', $script]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to list Apple Notes: '.$result->errorOutput());
        }

        $raw = trim($result->output());
        $names = $raw !== '' ? explode(', ', $raw) : [];

        return ['notes' => $names];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getNote(array $params): array
    {
        $noteName = $this->escapeAppleScriptString((string) $params['noteId']);

        $script = <<<SCRIPT
            tell application "Notes"
                set theNote to first note whose name is {$noteName}
                return body of theNote
            end tell
            SCRIPT;

        $result = Process::run(['osascript', '-e', $script]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to get note: '.$result->errorOutput());
        }

        return [
            'name' => (string) $params['noteId'],
            'body' => trim($result->output()),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function addNote(array $params): array
    {
        $title = $this->escapeAppleScriptString((string) $params['title']);
        $body = $this->escapeAppleScriptString((string) $params['body']);
        $folder = isset($params['folder']) ? $this->escapeAppleScriptString((string) $params['folder']) : null;

        if ($folder !== null) {
            $script = <<<SCRIPT
                tell application "Notes"
                    set theFolder to first folder whose name is {$folder}
                    make new note at theFolder with properties {name:{$title}, body:{$body}}
                end tell
                SCRIPT;
        } else {
            $script = <<<SCRIPT
                tell application "Notes"
                    make new note with properties {name:{$title}, body:{$body}}
                end tell
                SCRIPT;
        }

        $result = Process::run(['osascript', '-e', $script]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to add note: '.$result->errorOutput());
        }

        return ['created' => true, 'title' => (string) $params['title']];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function updateNote(array $params): array
    {
        $noteName = $this->escapeAppleScriptString((string) $params['noteId']);
        $appendText = $this->escapeAppleScriptString("\n\n".(string) $params['body']);

        $script = <<<SCRIPT
            tell application "Notes"
                set theNote to first note whose name is {$noteName}
                set body of theNote to (body of theNote) & {$appendText}
            end tell
            SCRIPT;

        $result = Process::run(['osascript', '-e', $script]);

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to update note: '.$result->errorOutput());
        }

        return ['updated' => true, 'name' => (string) $params['noteId']];
    }

    private function escapeAppleScriptString(string $value): string
    {
        $escaped = str_replace('"', '\\"', $value);

        return '"'.$escaped.'"';
    }
}
