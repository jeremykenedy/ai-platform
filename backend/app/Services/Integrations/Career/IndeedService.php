<?php

declare(strict_types=1);

namespace App\Services\Integrations\Career;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class IndeedService extends AbstractIntegrationService
{
    protected string $integrationName = 'indeed';

    private const BASE_URL = 'https://apis.indeed.com/v2';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'search_jobs',
                'description' => 'Search for job postings on Indeed.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'Job title, keywords, or company.',
                        ],
                        'location' => [
                            'type'        => 'string',
                            'description' => 'City, state, or zip code.',
                        ],
                        'radius' => [
                            'type'        => 'integer',
                            'description' => 'Search radius in miles (default 25).',
                        ],
                        'sort' => [
                            'type'        => 'string',
                            'enum'        => ['relevance', 'date'],
                            'description' => 'Sort order for results (default "relevance").',
                        ],
                        'limit' => [
                            'type'        => 'integer',
                            'description' => 'Number of results to return (default 10, max 25).',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'get_job_details',
                'description' => 'Retrieve full details of a specific Indeed job posting.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'jobKey' => [
                            'type'        => 'string',
                            'description' => 'The Indeed job key (job ID).',
                        ],
                    ],
                    'required' => ['jobKey'],
                ],
            ],
            [
                'name'        => 'get_company',
                'description' => 'Retrieve company information from Indeed.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'companyName' => [
                            'type'        => 'string',
                            'description' => 'Name of the company to look up.',
                        ],
                    ],
                    'required' => ['companyName'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'search_jobs'     => $this->searchJobs($user, $params),
            'get_job_details' => $this->getJobDetails($user, $params),
            'get_company'     => $this->getCompany($user, $params),
            default           => throw new RuntimeException("Unknown tool: {$toolName}"),
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
            'q'      => $query,
            'limit'  => min((int) ($params['limit'] ?? 10), 25),
            'radius' => (int) ($params['radius'] ?? 25),
            'sort'   => $params['sort'] ?? 'relevance',
            'format' => 'json',
            'v'      => '2',
        ];

        if (isset($params['location'])) {
            $queryParams['l'] = $params['location'];
        }

        $response = $this->client($user)->get(self::BASE_URL.'/jobs/search', $queryParams);
        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getJobDetails(User $user, array $params): array
    {
        $jobKey = $params['jobKey'] ?? throw new RuntimeException('jobKey is required.');

        $response = $this->client($user)->get(self::BASE_URL.'/jobs/details', [
            'jobkeys' => $jobKey,
            'format'  => 'json',
            'v'       => '2',
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getCompany(User $user, array $params): array
    {
        $companyName = $params['companyName'] ?? throw new RuntimeException('companyName is required.');

        $response = $this->client($user)->get(self::BASE_URL.'/companies/search', [
            'q'      => $companyName,
            'format' => 'json',
        ]);

        $response->throw();

        return $response->json();
    }

    private function getApiKey(User $user): string
    {
        $credentials = $this->getCredentials($user);

        if ($credentials === null || empty($credentials['api_key'])) {
            throw new RuntimeException('Indeed API key is not configured for this user.');
        }

        return (string) $credentials['api_key'];
    }

    private function client(User $user): PendingRequest
    {
        return Http::withToken($this->getApiKey($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->acceptJson();
    }
}
