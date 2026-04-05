<?php

declare(strict_types=1);

namespace App\Services\Integrations\Productivity;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Http;

class NotionService extends AbstractIntegrationService
{
    protected string $integrationName = 'notion';

    private const BASE_URL = 'https://api.notion.com/v1';

    private const NOTION_VERSION = '2022-06-28';

    public function getAuthUrl(User $user): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function handleCallback(User $user, array $params): void {}

    public function isConnected(User $user): bool
    {
        $credentials = $this->getCredentials($user);

        return isset($credentials['api_token']) && (string) $credentials['api_token'] !== '';
    }

    /**
     * Store Notion API token for the user.
     *
     * @param  array<string, mixed>  $params  Must contain 'api_token'.
     */
    public function connect(User $user, array $params): void
    {
        $definition = $this->getDefinition();

        UserIntegration::updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'integration_id' => $definition->getKey(),
            ],
            [
                'is_enabled' => true,
                'credentials' => ['api_token' => (string) $params['api_token']],
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
                'name' => 'search',
                'description' => 'Search across all pages and databases in Notion.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['query'],
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search query string.'],
                    ],
                ],
            ],
            [
                'name' => 'get_page',
                'description' => 'Retrieve a Notion page by ID.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['pageId'],
                    'properties' => [
                        'pageId' => ['type' => 'string', 'description' => 'Notion page ID.'],
                    ],
                ],
            ],
            [
                'name' => 'create_page',
                'description' => 'Create a new page in Notion.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['parentId', 'title'],
                    'properties' => [
                        'parentId' => ['type' => 'string', 'description' => 'Parent page or database ID.'],
                        'title' => ['type' => 'string', 'description' => 'Page title.'],
                        'content' => ['type' => 'string', 'description' => 'Page body text content.'],
                    ],
                ],
            ],
            [
                'name' => 'update_page',
                'description' => 'Update properties of a Notion page.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['pageId', 'properties'],
                    'properties' => [
                        'pageId' => ['type' => 'string', 'description' => 'Notion page ID.'],
                        'properties' => [
                            'type' => 'object',
                            'description' => 'Properties to update as key-value pairs.',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'query_database',
                'description' => 'Query a Notion database with optional filters.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['databaseId'],
                    'properties' => [
                        'databaseId' => ['type' => 'string', 'description' => 'Notion database ID.'],
                        'filter' => [
                            'type' => 'object',
                            'description' => 'Notion filter object.',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'create_database_entry',
                'description' => 'Create a new entry (row) in a Notion database.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['databaseId', 'properties'],
                    'properties' => [
                        'databaseId' => ['type' => 'string', 'description' => 'Notion database ID.'],
                        'properties' => [
                            'type' => 'object',
                            'description' => 'Database entry properties matching the database schema.',
                            'additionalProperties' => true,
                        ],
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
        return match ($toolName) {
            'search' => $this->search($params, $user),
            'get_page' => $this->getPage($params, $user),
            'create_page' => $this->createPage($params, $user),
            'update_page' => $this->updatePage($params, $user),
            'query_database' => $this->queryDatabase($params, $user),
            'create_database_entry' => $this->createDatabaseEntry($params, $user),
            default => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    public function testConnection(User $user): bool
    {
        try {
            $response = $this->makeRequest('get', '/users/me', $user);

            return isset($response['id']);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function search(array $params, User $user): array
    {
        $response = Http::withToken($this->getApiToken($user))
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/search', [
                'query' => (string) $params['query'],
                'page_size' => 20,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getPage(array $params, User $user): array
    {
        $pageId = $this->formatId((string) $params['pageId']);

        $response = Http::withToken($this->getApiToken($user))
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/pages/'.$pageId);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createPage(array $params, User $user): array
    {
        $parentId = $this->formatId((string) $params['parentId']);
        $title = (string) $params['title'];

        $body = [
            'parent' => ['page_id' => $parentId],
            'properties' => [
                'title' => [
                    'title' => [
                        ['type' => 'text', 'text' => ['content' => $title]],
                    ],
                ],
            ],
        ];

        if (isset($params['content'])) {
            $body['children'] = [
                [
                    'object' => 'block',
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [
                            ['type' => 'text', 'text' => ['content' => (string) $params['content']]],
                        ],
                    ],
                ],
            ];
        }

        $response = Http::withToken($this->getApiToken($user))
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/pages', $body);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function updatePage(array $params, User $user): array
    {
        $pageId = $this->formatId((string) $params['pageId']);
        $properties = (array) $params['properties'];

        $response = Http::withToken($this->getApiToken($user))
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->timeout(30)
            ->connectTimeout(10)
            ->patch(self::BASE_URL.'/pages/'.$pageId, ['properties' => $properties]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function queryDatabase(array $params, User $user): array
    {
        $databaseId = $this->formatId((string) $params['databaseId']);

        $body = array_filter([
            'filter' => $params['filter'] ?? null,
            'page_size' => 50,
        ]);

        $response = Http::withToken($this->getApiToken($user))
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/databases/'.$databaseId.'/query', $body);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createDatabaseEntry(array $params, User $user): array
    {
        $databaseId = $this->formatId((string) $params['databaseId']);
        $properties = (array) $params['properties'];

        $response = Http::withToken($this->getApiToken($user))
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/pages', [
                'parent' => ['database_id' => $databaseId],
                'properties' => $properties,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function makeRequest(string $method, string $path, User $user, array $params = []): array
    {
        $request = Http::withToken($this->getApiToken($user))
            ->withHeaders(['Notion-Version' => self::NOTION_VERSION])
            ->timeout(30)
            ->connectTimeout(10);

        $response = match ($method) {
            'get' => $request->get(self::BASE_URL.$path, $params),
            'post' => $request->post(self::BASE_URL.$path, $params),
            'patch' => $request->patch(self::BASE_URL.$path, $params),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        $response->throw();

        return $response->json() ?? [];
    }

    private function getApiToken(User $user): string
    {
        $credentials = $this->getCredentials($user);

        return (string) ($credentials['api_token'] ?? '');
    }

    private function formatId(string $id): string
    {
        return str_replace('-', '', $id);
    }
}
