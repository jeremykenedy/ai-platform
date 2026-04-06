<?php

declare(strict_types=1);

namespace App\Services\Integrations\Career;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiceService extends AbstractIntegrationService
{
    protected string $integrationName = 'dice';

    private const BASE_URL = 'https://job-search-api.svc.dhigroupinc.com/v1/dice';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'search_jobs',
                'description' => 'Search for technology and engineering jobs on Dice.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'Job title, keywords, or skills.',
                        ],
                        'location' => [
                            'type'        => 'string',
                            'description' => 'City, state, or zip code.',
                        ],
                        'radius' => [
                            'type'        => 'integer',
                            'description' => 'Search radius in miles (default 30).',
                        ],
                        'sort' => [
                            'type'        => 'string',
                            'enum'        => ['relevance', 'date'],
                            'description' => 'Sort order (default "relevance").',
                        ],
                        'page' => [
                            'type'        => 'integer',
                            'description' => 'Page number for pagination (default 1).',
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
            'search_jobs' => $this->searchJobs($user, $params),
            default       => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function searchJobs(User $user, array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');

        $queryParams = [
            'q'            => $query,
            'countryCode2' => 'US',
            'radius'       => (int) ($params['radius'] ?? 30),
            'radiusUnit'   => 'mi',
            'page'         => (int) ($params['page'] ?? 1),
            'pageSize'     => 20,
            'facets'       => 'employmentType|postedDate|workFromHomeAvailability|employerType|easyApply|isRemote',
            'sort'         => $params['sort'] ?? 'relevance',
        ];

        if (isset($params['location'])) {
            $queryParams['location'] = $params['location'];
        }

        $response = $this->client($user)->get(self::BASE_URL.'/jobs', $queryParams);
        $response->throw();

        return $response->json();
    }

    private function getApiKey(User $user): string
    {
        $credentials = $this->getCredentials($user);

        if ($credentials === null || empty($credentials['api_key'])) {
            throw new RuntimeException('Dice API key is not configured for this user.');
        }

        return (string) $credentials['api_key'];
    }

    private function client(User $user): PendingRequest
    {
        return Http::withHeaders(['X-Api-Key' => $this->getApiKey($user)])
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->acceptJson();
    }
}
