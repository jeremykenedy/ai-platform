<?php

declare(strict_types=1);

namespace App\Services\Integrations\Developer;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PostmanService extends AbstractIntegrationService
{
    protected string $integrationName = 'postman';

    private const BASE_URL = 'https://api.getpostman.com';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'list_collections',
                'description' => 'List all Postman collections in the user account.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_collection',
                'description' => 'Get a specific Postman collection by ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'collectionId' => ['type' => 'string', 'description' => 'The collection UID (e.g. "12345678-abcd-efgh-ijkl-mnopqrstuvwx").'],
                    ],
                    'required' => ['collectionId'],
                ],
            ],
            [
                'name' => 'list_environments',
                'description' => 'List all Postman environments in the user account.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_environment',
                'description' => 'Get a specific Postman environment by ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'environmentId' => ['type' => 'string', 'description' => 'The environment UID.'],
                    ],
                    'required' => ['environmentId'],
                ],
            ],
            [
                'name' => 'list_workspaces',
                'description' => 'List all Postman workspaces.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'create_collection',
                'description' => 'Create a new Postman collection.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Name for the new collection.'],
                        'description' => ['type' => 'string', 'description' => 'Optional description for the collection.'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'run_collection',
                'description' => 'Trigger a collection run via the Postman API.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'collectionId' => ['type' => 'string', 'description' => 'The collection UID to run.'],
                    ],
                    'required' => ['collectionId'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_collections' => $this->listCollections($params, $user),
            'get_collection' => $this->getCollection($params, $user),
            'list_environments' => $this->listEnvironments($params, $user),
            'get_environment' => $this->getEnvironment($params, $user),
            'list_workspaces' => $this->listWorkspaces($params, $user),
            'create_collection' => $this->createCollection($params, $user),
            'run_collection' => $this->runCollection($params, $user),
            default => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    private function makeClient(User $user): PendingRequest
    {
        $credentials = $this->getCredentials($user);
        $apiKey = (string) ($credentials['api_key'] ?? config('services.postman.api_key', ''));

        return Http::baseUrl(self::BASE_URL)
            ->timeout(20)
            ->connectTimeout(10)
            ->withHeaders([
                'X-Api-Key' => $apiKey,
            ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listCollections(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/collections');

            if ($response->failed()) {
                return ['error' => $response->json('error.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getCollection(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/collections/{$params['collectionId']}");

            if ($response->failed()) {
                return ['error' => $response->json('error.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listEnvironments(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/environments');

            if ($response->failed()) {
                return ['error' => $response->json('error.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getEnvironment(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/environments/{$params['environmentId']}");

            if ($response->failed()) {
                return ['error' => $response->json('error.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listWorkspaces(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/workspaces');

            if ($response->failed()) {
                return ['error' => $response->json('error.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createCollection(array $params, User $user): array
    {
        try {
            $body = [
                'collection' => [
                    'info' => [
                        'name' => $params['name'],
                        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
                    ],
                ],
            ];

            if (isset($params['description'])) {
                $body['collection']['info']['description'] = $params['description'];
            }

            $response = $this->makeClient($user)->post('/collections', $body);

            if ($response->failed()) {
                return ['error' => $response->json('error.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function runCollection(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->post('/collections/'.$params['collectionId'].'/runs');

            if ($response->failed()) {
                return ['error' => $response->json('error.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
