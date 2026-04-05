<?php

declare(strict_types=1);

namespace App\Services\Integrations\Local;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FilesystemService extends AbstractIntegrationService
{
    protected string $integrationName = 'filesystem';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'list_files',
                'description' => 'List files and directories at the given storage path.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path within storage/app (default "/").',
                        ],
                        'recursive' => [
                            'type' => 'boolean',
                            'description' => 'Whether to list files recursively (default false).',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'read_file',
                'description' => 'Read the contents of a file from local storage.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path to the file within storage/app.',
                        ],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'write_file',
                'description' => 'Write content to a file in local storage, creating it if it does not exist.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path to write within storage/app.',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Text content to write to the file.',
                        ],
                    ],
                    'required' => ['path', 'content'],
                ],
            ],
            [
                'name' => 'file_info',
                'description' => 'Get metadata about a file (size, last modified, mime type).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Relative path to the file within storage/app.',
                        ],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'search_files',
                'description' => 'Search for files matching a glob pattern within a storage path.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Base directory to search within storage/app (default "/").',
                        ],
                        'pattern' => [
                            'type' => 'string',
                            'description' => 'Glob pattern to match filenames (e.g. "*.txt", "report_*").',
                        ],
                    ],
                    'required' => ['pattern'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_files' => $this->listFiles($params),
            'read_file' => $this->readFile($params),
            'write_file' => $this->writeFile($params),
            'file_info' => $this->fileInfo($params),
            'search_files' => $this->searchFiles($params),
            default => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    public function isConnected(User $user): bool
    {
        return true;
    }

    public function testConnection(User $user): bool
    {
        return Storage::disk('local')->exists('.');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listFiles(array $params): array
    {
        $path = $this->sanitizePath($params['path'] ?? '/');
        $recursive = (bool) ($params['recursive'] ?? false);

        $disk = Storage::disk('local');

        $files = $recursive ? $disk->allFiles($path) : $disk->files($path);
        $directories = $recursive ? $disk->allDirectories($path) : $disk->directories($path);

        return [
            'path' => $path,
            'files' => $files,
            'directories' => $directories,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function readFile(array $params): array
    {
        $path = $this->sanitizePath($params['path'] ?? throw new RuntimeException('path is required.'));

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $content = $disk->get($path);

        return [
            'path' => $path,
            'content' => $content,
            'size' => $disk->size($path),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function writeFile(array $params): array
    {
        $path = $this->sanitizePath($params['path'] ?? throw new RuntimeException('path is required.'));
        $content = $params['content'] ?? throw new RuntimeException('content is required.');

        Storage::disk('local')->put($path, (string) $content);

        return [
            'path' => $path,
            'written' => true,
            'size' => Storage::disk('local')->size($path),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function fileInfo(array $params): array
    {
        $path = $this->sanitizePath($params['path'] ?? throw new RuntimeException('path is required.'));

        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        return [
            'path' => $path,
            'size' => $disk->size($path),
            'last_modified' => $disk->lastModified($path),
            'mime_type' => $disk->mimeType($path),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function searchFiles(array $params): array
    {
        $basePath = $this->sanitizePath($params['path'] ?? '/');
        $pattern = $params['pattern'] ?? throw new RuntimeException('pattern is required.');

        $disk = Storage::disk('local');
        $allFiles = $disk->allFiles($basePath);

        $matched = array_values(array_filter(
            $allFiles,
            static fn (string $file): bool => fnmatch($pattern, basename($file)),
        ));

        return [
            'path' => $basePath,
            'pattern' => $pattern,
            'matches' => $matched,
            'count' => count($matched),
        ];
    }

    /**
     * Sanitize a path to prevent directory traversal outside storage/app.
     */
    private function sanitizePath(string $path): string
    {
        // Normalize separators and strip dangerous components.
        $path = str_replace(['\\', "\0"], '/', $path);
        $path = ltrim($path, '/');

        $parts = explode('/', $path);
        $safe = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                // Never allow traversal upward.
                throw new RuntimeException('Path traversal is not allowed.');
            }

            $safe[] = $part;
        }

        return implode('/', $safe);
    }
}
