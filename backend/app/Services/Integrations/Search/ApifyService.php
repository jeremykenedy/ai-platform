<?php

declare(strict_types=1);

namespace App\Services\Integrations\Search;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApifyService extends AbstractIntegrationService
{
    protected string $integrationName = 'apify';

    private const BASE_URL = 'https://api.apify.com/v2';

    /**
     * The Apify actor ID used for URL scraping.
     */
    private const SCRAPER_ACTOR = 'apify/website-content-crawler';

    /**
     * The Apify actor ID used for search-and-scrape.
     */
    private const SEARCH_ACTOR = 'apify/google-search-scraper';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'run_actor',
                'description' => 'Run an Apify actor synchronously and return its output.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'actorId' => [
                            'type'        => 'string',
                            'description' => 'The Apify actor ID or name (e.g. "apify/web-scraper").',
                        ],
                        'input' => [
                            'type'        => 'object',
                            'description' => 'Actor input as a JSON object.',
                        ],
                    ],
                    'required' => ['actorId', 'input'],
                ],
            ],
            [
                'name'        => 'get_run_output',
                'description' => 'Retrieve the dataset output of a completed Apify actor run.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'runId' => [
                            'type'        => 'string',
                            'description' => 'The Apify run ID.',
                        ],
                    ],
                    'required' => ['runId'],
                ],
            ],
            [
                'name'        => 'scrape_url',
                'description' => 'Scrape the content of a URL using Apify.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'url' => [
                            'type'        => 'string',
                            'description' => 'The URL to scrape.',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
            [
                'name'        => 'search_and_scrape',
                'description' => 'Perform a Google search via Apify and scrape the results.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'The search query.',
                        ],
                        'maxResults' => [
                            'type'        => 'integer',
                            'description' => 'Maximum number of search results to return (default 10).',
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
            'run_actor'         => $this->runActor($user, $params),
            'get_run_output'    => $this->getRunOutput($user, $params),
            'scrape_url'        => $this->scrapeUrl($user, $params),
            'search_and_scrape' => $this->searchAndScrape($user, $params),
            default             => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function runActor(User $user, array $params): array
    {
        $actorId = $params['actorId'] ?? throw new RuntimeException('actorId is required.');
        $input = $params['input'] ?? [];

        if (!is_array($input)) {
            throw new RuntimeException('input must be a JSON object.');
        }

        $response = $this->client($user)
            ->post(self::BASE_URL.'/acts/'.urlencode((string) $actorId).'/run-sync-get-dataset-items', $input);

        $response->throw();

        return ['items' => $response->json()];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getRunOutput(User $user, array $params): array
    {
        $runId = $params['runId'] ?? throw new RuntimeException('runId is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/actor-runs/'.urlencode((string) $runId).'/dataset/items');

        $response->throw();

        return ['items' => $response->json()];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function scrapeUrl(User $user, array $params): array
    {
        $url = $params['url'] ?? throw new RuntimeException('url is required.');

        $response = $this->client($user)
            ->post(self::BASE_URL.'/acts/'.urlencode(self::SCRAPER_ACTOR).'/run-sync-get-dataset-items', [
                'startUrls'        => [['url' => $url]],
                'maxCrawlingDepth' => 0,
                'maxResults'       => 1,
            ]);

        $response->throw();

        $items = $response->json();

        return ['items' => $items, 'url' => $url];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function searchAndScrape(User $user, array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');
        $maxResults = (int) ($params['maxResults'] ?? 10);

        $response = $this->client($user)
            ->post(self::BASE_URL.'/acts/'.urlencode(self::SEARCH_ACTOR).'/run-sync-get-dataset-items', [
                'queries'          => $query,
                'maxPagesPerQuery' => 1,
                'resultsPerPage'   => $maxResults,
                'mobileResults'    => false,
            ]);

        $response->throw();

        return ['items' => $response->json(), 'query' => $query];
    }

    private function getApiKey(User $user): string
    {
        $credentials = $this->getCredentials($user);

        if ($credentials === null || empty($credentials['api_key'])) {
            throw new RuntimeException('Apify API key is not configured for this user.');
        }

        return (string) $credentials['api_key'];
    }

    private function client(User $user): PendingRequest
    {
        return Http::withQueryParameters(['token' => $this->getApiKey($user)])
            ->timeout(120)
            ->connectTimeout(10)
            ->acceptJson();
    }
}
