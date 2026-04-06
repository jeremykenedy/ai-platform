<?php

declare(strict_types=1);

namespace App\Services\Integrations\Search;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SearXNGService extends AbstractIntegrationService
{
    protected string $integrationName = 'searxng';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'web_search',
                'description' => 'Search the web via a self-hosted SearXNG instance.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'The search query.',
                        ],
                        'categories' => [
                            'type'        => 'string',
                            'description' => 'Comma-separated search categories (e.g. "general,science").',
                        ],
                        'engines' => [
                            'type'        => 'string',
                            'description' => 'Comma-separated search engines to use (e.g. "google,bing").',
                        ],
                        'language' => [
                            'type'        => 'string',
                            'description' => 'Language code for results (e.g. "en-US").',
                        ],
                        'pageno' => [
                            'type'        => 'integer',
                            'description' => 'Page number for pagination (default 1).',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'news_search',
                'description' => 'Search news articles via a self-hosted SearXNG instance.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'The news search query.',
                        ],
                        'language' => [
                            'type'        => 'string',
                            'description' => 'Language code for results (e.g. "en-US").',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'web_search'  => $this->webSearch($params),
            'news_search' => $this->newsSearch($params),
            default       => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    public function isConnected(User $user): bool
    {
        return true;
    }

    public function testConnection(User $user): bool
    {
        try {
            $response = $this->client()
                ->timeout(5)
                ->get($this->baseUrl().'/healthz');

            if ($response->successful()) {
                return true;
            }

            // Some SearXNG instances return 200 on the root path but not /healthz.
            $rootResponse = $this->client()
                ->timeout(5)
                ->get($this->baseUrl().'/');

            return $rootResponse->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function webSearch(array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');

        $queryParams = [
            'q'          => $query,
            'format'     => 'json',
            'categories' => $params['categories'] ?? 'general',
            'pageno'     => (int) ($params['pageno'] ?? 1),
        ];

        if (isset($params['engines'])) {
            $queryParams['engines'] = $params['engines'];
        }

        if (isset($params['language'])) {
            $queryParams['language'] = $params['language'];
        }

        $response = $this->client()->get($this->baseUrl().'/search', $queryParams);
        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function newsSearch(array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');

        $queryParams = [
            'q'          => $query,
            'format'     => 'json',
            'categories' => 'news',
            'pageno'     => 1,
        ];

        if (isset($params['language'])) {
            $queryParams['language'] = $params['language'];
        }

        $response = $this->client()->get($this->baseUrl().'/search', $queryParams);
        $response->throw();

        return $response->json();
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.searxng.base_url', 'http://searxng:8080'), '/');
    }

    private function client(): PendingRequest
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->acceptJson();
    }
}
