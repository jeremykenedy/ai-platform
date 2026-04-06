<?php

declare(strict_types=1);

namespace App\Services\Integrations\Search;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BraveSearchService extends AbstractIntegrationService
{
    protected string $integrationName = 'brave_search';

    private const BASE_URL = 'https://api.search.brave.com/res/v1';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'web_search',
                'description' => 'Search the web using Brave Search.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'The search query.',
                        ],
                        'count' => [
                            'type'        => 'integer',
                            'description' => 'Number of results to return (default 10, max 20).',
                        ],
                        'freshness' => [
                            'type'        => 'string',
                            'enum'        => ['pd', 'pw', 'pm', 'py'],
                            'description' => 'Time freshness filter: pd=past day, pw=past week, pm=past month, py=past year.',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'news_search',
                'description' => 'Search news articles using Brave Search.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'The news search query.',
                        ],
                        'count' => [
                            'type'        => 'integer',
                            'description' => 'Number of results to return (default 10, max 20).',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'image_search',
                'description' => 'Search images using Brave Search.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'The image search query.',
                        ],
                        'count' => [
                            'type'        => 'integer',
                            'description' => 'Number of results to return (default 10, max 20).',
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
            'web_search'   => $this->webSearch($user, $params),
            'news_search'  => $this->newsSearch($user, $params),
            'image_search' => $this->imageSearch($user, $params),
            default        => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function webSearch(User $user, array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');
        $count = min((int) ($params['count'] ?? 10), 20);

        $queryParams = [
            'q'     => $query,
            'count' => $count,
        ];

        if (isset($params['freshness'])) {
            $queryParams['freshness'] = $params['freshness'];
        }

        $response = $this->client($user)->get(self::BASE_URL.'/web/search', $queryParams);
        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function newsSearch(User $user, array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');
        $count = min((int) ($params['count'] ?? 10), 20);

        $response = $this->client($user)->get(self::BASE_URL.'/news/search', [
            'q'     => $query,
            'count' => $count,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function imageSearch(User $user, array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');
        $count = min((int) ($params['count'] ?? 10), 20);

        $response = $this->client($user)->get(self::BASE_URL.'/images/search', [
            'q'     => $query,
            'count' => $count,
        ]);

        $response->throw();

        return $response->json();
    }

    private function getApiKey(User $user): string
    {
        $credentials = $this->getCredentials($user);

        if ($credentials === null || empty($credentials['api_key'])) {
            throw new RuntimeException('Brave Search API key is not configured for this user.');
        }

        return (string) $credentials['api_key'];
    }

    private function client(User $user): PendingRequest
    {
        return Http::withHeaders([
            'X-Subscription-Token' => $this->getApiKey($user),
            'Accept'               => 'application/json',
        ])
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500);
    }
}
