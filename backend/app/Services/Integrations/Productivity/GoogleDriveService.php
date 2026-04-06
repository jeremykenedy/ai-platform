<?php

declare(strict_types=1);

namespace App\Services\Integrations\Productivity;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Http;

class GoogleDriveService extends AbstractIntegrationService
{
    protected string $integrationName = 'google_drive';

    private const BASE_URL = 'https://www.googleapis.com/drive/v3';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const SCOPES = [
        'https://www.googleapis.com/auth/drive.readonly',
    ];

    public function getAuthUrl(User $user): ?string
    {
        $params = http_build_query([
            'client_id'     => config('services.google.client_id'),
            'redirect_uri'  => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $user->getKey(),
        ]);

        return self::AUTH_URL.'?'.$params;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function handleCallback(User $user, array $params): void
    {
        $code = (string) ($params['code'] ?? '');

        $response = Http::asForm()
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::TOKEN_URL, [
                'code'          => $code,
                'client_id'     => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri'  => config('services.google.redirect_uri'),
                'grant_type'    => 'authorization_code',
            ]);

        $response->throw();

        $data = $response->json();

        $definition = $this->getDefinition();

        UserIntegration::updateOrCreate(
            [
                'user_id'        => $user->getKey(),
                'integration_id' => $definition->getKey(),
            ],
            [
                'is_enabled'          => true,
                'oauth_token'         => $data['access_token'] ?? null,
                'oauth_refresh_token' => $data['refresh_token'] ?? null,
                'oauth_expires_at'    => isset($data['expires_in'])
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
                'scopes_granted' => self::SCOPES,
            ],
        );
    }

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'search_files',
                'description' => 'Search for files in Google Drive.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['query'],
                    'properties' => [
                        'query'    => ['type' => 'string', 'description' => 'Drive search query (e.g. "name contains \'report\'").'],
                        'mimeType' => ['type' => 'string', 'description' => 'Filter by MIME type (e.g. "application/vnd.google-apps.document").'],
                    ],
                ],
            ],
            [
                'name'        => 'read_file',
                'description' => 'Read the content of a Google Drive file (exported as plain text).',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['fileId'],
                    'properties' => [
                        'fileId' => ['type' => 'string', 'description' => 'Google Drive file ID.'],
                    ],
                ],
            ],
            [
                'name'        => 'list_directory',
                'description' => 'List files in a Google Drive folder.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'folderId' => ['type' => 'string', 'description' => 'Folder ID (defaults to root).'],
                    ],
                ],
            ],
            [
                'name'        => 'get_file_info',
                'description' => 'Get metadata for a Google Drive file.',
                'parameters'  => [
                    'type'       => 'object',
                    'required'   => ['fileId'],
                    'properties' => [
                        'fileId' => ['type' => 'string', 'description' => 'Google Drive file ID.'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'search_files'   => $this->searchFiles($params, $user),
            'read_file'      => $this->readFile($params, $user),
            'list_directory' => $this->listDirectory($params, $user),
            'get_file_info'  => $this->getFileInfo($params, $user),
            default          => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    public function testConnection(User $user): bool
    {
        try {
            $this->listDirectory([], $user);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function searchFiles(array $params, User $user): array
    {
        $queryParts = [(string) $params['query']];

        if (isset($params['mimeType'])) {
            $queryParts[] = "mimeType='{$params['mimeType']}'";
        }

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/files', [
                'q'        => implode(' and ', $queryParts),
                'fields'   => 'files(id,name,mimeType,size,modifiedTime,webViewLink,parents)',
                'pageSize' => 20,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function readFile(array $params, User $user): array
    {
        $fileId = (string) $params['fileId'];

        $meta = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/files/'.$fileId, ['fields' => 'id,name,mimeType'])
            ->throw()
            ->json();

        $mimeType = (string) ($meta['mimeType'] ?? '');

        if (str_starts_with($mimeType, 'application/vnd.google-apps.')) {
            $exportMime = match ($mimeType) {
                'application/vnd.google-apps.spreadsheet'  => 'text/csv',
                'application/vnd.google-apps.presentation' => 'text/plain',
                default                                    => 'text/plain',
            };

            $content = Http::withToken($this->getOauthToken($user))
                ->timeout(60)
                ->connectTimeout(10)
                ->get(self::BASE_URL.'/files/'.$fileId.'/export', ['mimeType' => $exportMime])
                ->throw()
                ->body();

            return ['fileId' => $fileId, 'name' => $meta['name'] ?? '', 'content' => $content];
        }

        $content = Http::withToken($this->getOauthToken($user))
            ->timeout(60)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/files/'.$fileId, ['alt' => 'media'])
            ->throw()
            ->body();

        return ['fileId' => $fileId, 'name' => $meta['name'] ?? '', 'content' => $content];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listDirectory(array $params, User $user): array
    {
        $folderId = (string) ($params['folderId'] ?? 'root');

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/files', [
                'q'        => "'{$folderId}' in parents and trashed = false",
                'fields'   => 'files(id,name,mimeType,size,modifiedTime,webViewLink)',
                'pageSize' => 50,
                'orderBy'  => 'name',
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getFileInfo(array $params, User $user): array
    {
        $fileId = (string) $params['fileId'];

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/files/'.$fileId, [
                'fields' => 'id,name,mimeType,size,createdTime,modifiedTime,webViewLink,parents,owners,shared',
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    private function getOauthToken(User $user): string
    {
        $integration = $this->getUserIntegration($user);

        return (string) ($integration?->oauth_token ?? '');
    }
}
