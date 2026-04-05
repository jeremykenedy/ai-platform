<?php

declare(strict_types=1);

namespace App\Services\Integrations\Local;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class MacOSService extends AbstractIntegrationService
{
    protected string $integrationName = 'macos';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'get_clipboard',
                'description' => 'Read the current text content of the macOS clipboard.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'set_clipboard',
                'description' => 'Write text to the macOS clipboard.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => [
                            'type' => 'string',
                            'description' => 'The text to place on the clipboard.',
                        ],
                    ],
                    'required' => ['text'],
                ],
            ],
            [
                'name' => 'open_url',
                'description' => 'Open a URL in the default browser on macOS.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'The URL to open.',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
            [
                'name' => 'get_frontmost_app',
                'description' => 'Get the name of the currently active application on macOS.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'run_shortcut',
                'description' => 'Run a macOS Shortcut by name with optional input.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'shortcutName' => [
                            'type' => 'string',
                            'description' => 'The exact name of the Shortcut as it appears in the Shortcuts app.',
                        ],
                        'input' => [
                            'type' => 'string',
                            'description' => 'Optional text input to pass to the Shortcut.',
                        ],
                    ],
                    'required' => ['shortcutName'],
                ],
            ],
            [
                'name' => 'list_reminders',
                'description' => 'List reminders from a specific Reminders list on macOS.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'list' => [
                            'type' => 'string',
                            'description' => 'Name of the Reminders list (e.g. "Reminders", "Work").',
                        ],
                    ],
                    'required' => ['list'],
                ],
            ],
            [
                'name' => 'add_reminder',
                'description' => 'Add a reminder to a Reminders list on macOS.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'The title/text of the reminder.',
                        ],
                        'list' => [
                            'type' => 'string',
                            'description' => 'Name of the Reminders list to add to.',
                        ],
                        'dueDate' => [
                            'type' => 'string',
                            'description' => 'Optional due date/time (e.g. "tomorrow at 9am", "2024-06-01 10:00").',
                        ],
                    ],
                    'required' => ['title', 'list'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        $this->assertMacOS();

        return match ($toolName) {
            'get_clipboard' => $this->getClipboard(),
            'set_clipboard' => $this->setClipboard($params),
            'open_url' => $this->openUrl($params),
            'get_frontmost_app' => $this->getFrontmostApp(),
            'run_shortcut' => $this->runShortcut($params),
            'list_reminders' => $this->listReminders($params),
            'add_reminder' => $this->addReminder($params),
            default => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    public function isConnected(User $user): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    public function testConnection(User $user): bool
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return false;
        }

        try {
            $result = Process::run('osascript -e "return 1"');

            return $result->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getClipboard(): array
    {
        $result = $this->runAppleScript('get the clipboard');
        $this->assertSuccess($result, 'get_clipboard');

        return ['content' => trim($result->output())];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function setClipboard(array $params): array
    {
        $text = $params['text'] ?? throw new RuntimeException('text is required.');

        $escaped = str_replace(['"', '\\'], ['\"', '\\\\'], (string) $text);
        $result = $this->runAppleScript("set the clipboard to \"{$escaped}\"");
        $this->assertSuccess($result, 'set_clipboard');

        return ['success' => true];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function openUrl(array $params): array
    {
        $url = $params['url'] ?? throw new RuntimeException('url is required.');

        // Validate URL before passing to shell.
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid URL provided.');
        }

        $escaped = escapeshellarg($url);
        $result = Process::run("open {$escaped}");
        $this->assertSuccess($result, 'open_url');

        return ['success' => true, 'url' => $url];
    }

    /**
     * @return array<string, mixed>
     */
    private function getFrontmostApp(): array
    {
        $script = 'tell application "System Events" to get name of first application process whose frontmost is true';
        $result = $this->runAppleScript($script);
        $this->assertSuccess($result, 'get_frontmost_app');

        return ['app' => trim($result->output())];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function runShortcut(array $params): array
    {
        $shortcutName = $params['shortcutName'] ?? throw new RuntimeException('shortcutName is required.');
        $input = $params['input'] ?? null;

        // Use the Shortcuts CLI.
        $command = ['shortcuts', 'run', $shortcutName];

        if ($input !== null) {
            $command[] = '--input-path';
            $command[] = '-'; // Read from stdin.
        }

        $process = Process::input($input ?? '')->run(implode(' ', array_map('escapeshellarg', $command)));

        if (! $process->successful()) {
            throw new RuntimeException(
                "Shortcut '{$shortcutName}' failed: ".trim($process->errorOutput())
            );
        }

        return ['success' => true, 'output' => trim($process->output())];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listReminders(array $params): array
    {
        $list = $params['list'] ?? throw new RuntimeException('list is required.');
        $escapedList = str_replace(['"', '\\'], ['\"', '\\\\'], (string) $list);

        $script = <<<APPLESCRIPT
            tell application "Reminders"
                set theList to list "{$escapedList}"
                set theReminders to reminders of theList
                set output to ""
                repeat with r in theReminders
                    set output to output & name of r & "\n"
                end repeat
                return output
            end tell
            APPLESCRIPT;

        $result = $this->runAppleScript($script);
        $this->assertSuccess($result, 'list_reminders');

        $lines = array_values(array_filter(explode("\n", trim($result->output()))));

        return ['list' => $list, 'reminders' => $lines];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function addReminder(array $params): array
    {
        $title = $params['title'] ?? throw new RuntimeException('title is required.');
        $list = $params['list'] ?? throw new RuntimeException('list is required.');
        $dueDate = $params['dueDate'] ?? null;

        $escapedTitle = str_replace(['"', '\\'], ['\"', '\\\\'], (string) $title);
        $escapedList = str_replace(['"', '\\'], ['\"', '\\\\'], (string) $list);

        $dueDateScript = '';

        if ($dueDate !== null) {
            $escapedDate = str_replace(['"', '\\'], ['\"', '\\\\'], (string) $dueDate);
            $dueDateScript = "set due date of newReminder to date \"{$escapedDate}\"";
        }

        $script = <<<APPLESCRIPT
            tell application "Reminders"
                set theList to list "{$escapedList}"
                set newReminder to make new reminder at end of theList with properties {name: "{$escapedTitle}"}
                {$dueDateScript}
            end tell
            return "ok"
            APPLESCRIPT;

        $result = $this->runAppleScript($script);
        $this->assertSuccess($result, 'add_reminder');

        return ['success' => true, 'title' => $title, 'list' => $list];
    }

    private function runAppleScript(string $script): ProcessResult
    {
        return Process::run(['osascript', '-e', $script]);
    }

    private function assertSuccess(ProcessResult $result, string $tool): void
    {
        if (! $result->successful()) {
            throw new RuntimeException(
                "macOS tool '{$tool}' failed: ".trim($result->errorOutput())
            );
        }
    }

    private function assertMacOS(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            throw new RuntimeException('macOS tools are only available on macOS.');
        }
    }
}
