<?php

declare(strict_types=1);

namespace App\Services\Integrations\Developer;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class VercelService extends AbstractIntegrationService
{
    protected string $integrationName = 'vercel';

    private const BASE_URL = 'https://api.vercel.com';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'list_projects',
                'description' => 'List all Vercel projects in the account.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_project',
                'description' => 'Get details for a specific Vercel project.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Vercel project ID or name.'],
                    ],
                    'required' => ['projectId'],
                ],
            ],
            [
                'name' => 'list_deployments',
                'description' => 'List deployments for a Vercel project.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Vercel project ID or name.'],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum number of deployments to return.', 'default' => 20],
                    ],
                    'required' => ['projectId'],
                ],
            ],
            [
                'name' => 'get_deployment',
                'description' => 'Get details for a specific Vercel deployment.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'deploymentId' => ['type' => 'string', 'description' => 'Vercel deployment ID or URL.'],
                    ],
                    'required' => ['deploymentId'],
                ],
            ],
            [
                'name' => 'list_domains',
                'description' => 'List domains associated with a Vercel project.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Vercel project ID or name.'],
                    ],
                    'required' => ['projectId'],
                ],
            ],
            [
                'name' => 'list_env_vars',
                'description' => 'List environment variables for a Vercel project.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Vercel project ID or name.'],
                    ],
                    'required' => ['projectId'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_projects' => $this->listProjects($params, $user),
            'get_project' => $this->getProject($params, $user),
            'list_deployments' => $this->listDeployments($params, $user),
            'get_deployment' => $this->getDeployment($params, $user),
            'list_domains' => $this->listDomains($params, $user),
            'list_env_vars' => $this->listEnvVars($params, $user),
            default => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    private function makeClient(User $user): PendingRequest
    {
        $credentials = $this->getCredentials($user);
        $token = (string) ($credentials['api_token'] ?? config('services.vercel.api_token', ''));

        return Http::baseUrl(self::BASE_URL)
            ->timeout(20)
            ->connectTimeout(10)
            ->withToken($token);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listProjects(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/v9/projects');

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
    private function getProject(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/v9/projects/{$params['projectId']}");

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
    private function listDeployments(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/v6/deployments', [
                'projectId' => $params['projectId'],
                'limit' => $params['limit'] ?? 20,
            ]);

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
    private function getDeployment(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/v13/deployments/{$params['deploymentId']}");

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
    private function listDomains(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/v9/projects/{$params['projectId']}/domains");

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
    private function listEnvVars(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/v9/projects/{$params['projectId']}/env");

            if ($response->failed()) {
                return ['error' => $response->json('error.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
