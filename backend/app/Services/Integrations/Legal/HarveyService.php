<?php

declare(strict_types=1);

namespace App\Services\Integrations\Legal;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HarveyService extends AbstractIntegrationService
{
    protected string $integrationName = 'harvey';

    private const BASE_URL = 'https://api.harvey.ai/v1';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'legal_research',
                'description' => 'Perform AI-assisted legal research on a topic within a jurisdiction.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The legal research question or topic.',
                        ],
                        'jurisdiction' => [
                            'type' => 'string',
                            'description' => 'The jurisdiction to research within (e.g. "US Federal", "California", "UK").',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'document_analysis',
                'description' => 'Analyze a legal document using AI for key issues, risks, or summaries.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'documentText' => [
                            'type' => 'string',
                            'description' => 'The full text of the legal document to analyze.',
                        ],
                        'analysisType' => [
                            'type' => 'string',
                            'enum' => ['summary', 'risks', 'key_terms', 'obligations'],
                            'description' => 'Type of analysis to perform (default "summary").',
                        ],
                    ],
                    'required' => ['documentText'],
                ],
            ],
            [
                'name' => 'contract_review',
                'description' => 'Review a contract for issues, missing clauses, and negotiation points.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'contractText' => [
                            'type' => 'string',
                            'description' => 'The full text of the contract to review.',
                        ],
                    ],
                    'required' => ['contractText'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'legal_research' => $this->legalResearch($user, $params),
            'document_analysis' => $this->documentAnalysis($user, $params),
            'contract_review' => $this->contractReview($user, $params),
            default => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function legalResearch(User $user, array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');

        $body = ['query' => $query];

        if (isset($params['jurisdiction'])) {
            $body['jurisdiction'] = $params['jurisdiction'];
        }

        $response = $this->client($user)->post(self::BASE_URL.'/research', $body);
        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function documentAnalysis(User $user, array $params): array
    {
        $documentText = $params['documentText'] ?? throw new RuntimeException('documentText is required.');
        $analysisType = $params['analysisType'] ?? 'summary';

        $response = $this->client($user)->post(self::BASE_URL.'/documents/analyze', [
            'document' => $documentText,
            'analysis_type' => $analysisType,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function contractReview(User $user, array $params): array
    {
        $contractText = $params['contractText'] ?? throw new RuntimeException('contractText is required.');

        $response = $this->client($user)->post(self::BASE_URL.'/contracts/review', [
            'contract' => $contractText,
        ]);

        $response->throw();

        return $response->json();
    }

    private function getApiKey(User $user): string
    {
        $credentials = $this->getCredentials($user);

        if ($credentials === null || empty($credentials['api_key'])) {
            throw new RuntimeException('Harvey API key is not configured for this user.');
        }

        return (string) $credentials['api_key'];
    }

    private function client(User $user): PendingRequest
    {
        return Http::withToken($this->getApiKey($user))
            ->timeout(60)
            ->connectTimeout(10)
            ->retry(2, 1000)
            ->acceptJson();
    }
}
