<?php

declare(strict_types=1);

namespace App\Services\Integrations\Developer;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitLabService extends AbstractIntegrationService
{
    protected string $integrationName = 'gitlab';

    private const BASE_URL = 'https://gitlab.com/api/v4';

    private const OAUTH_AUTHORIZE_URL = 'https://gitlab.com/oauth/authorize';

    private const OAUTH_TOKEN_URL = 'https://gitlab.com/oauth/token';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'list_projects',
                'description' => 'List GitLab projects accessible to the authenticated user.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'per_page' => ['type' => 'integer', 'description' => 'Results per page (max 100).', 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name'        => 'get_project',
                'description' => 'Get details for a specific GitLab project.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Project ID or URL-encoded path (e.g. "group/project").'],
                    ],
                    'required' => ['projectId'],
                ],
            ],
            [
                'name'        => 'list_issues',
                'description' => 'List issues for a GitLab project.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Project ID or URL-encoded path.'],
                        'state'     => ['type' => 'string', 'description' => 'Issue state: opened, closed, all.', 'default' => 'opened'],
                    ],
                    'required' => ['projectId'],
                ],
            ],
            [
                'name'        => 'create_issue',
                'description' => 'Create a new issue in a GitLab project.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectId'   => ['type' => 'string', 'description' => 'Project ID or URL-encoded path.'],
                        'title'       => ['type' => 'string', 'description' => 'Issue title.'],
                        'description' => ['type' => 'string', 'description' => 'Issue description.'],
                    ],
                    'required' => ['projectId', 'title'],
                ],
            ],
            [
                'name'        => 'list_merge_requests',
                'description' => 'List merge requests for a GitLab project.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Project ID or URL-encoded path.'],
                        'state'     => ['type' => 'string', 'description' => 'MR state: opened, closed, locked, merged, all.', 'default' => 'opened'],
                    ],
                    'required' => ['projectId'],
                ],
            ],
            [
                'name'        => 'create_merge_request',
                'description' => 'Create a new merge request in a GitLab project.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectId'     => ['type' => 'string', 'description' => 'Project ID or URL-encoded path.'],
                        'title'         => ['type' => 'string', 'description' => 'Merge request title.'],
                        'source_branch' => ['type' => 'string', 'description' => 'The branch containing your changes.'],
                        'target_branch' => ['type' => 'string', 'description' => 'The branch to merge into.'],
                        'description'   => ['type' => 'string', 'description' => 'Merge request description.'],
                    ],
                    'required' => ['projectId', 'title', 'source_branch', 'target_branch'],
                ],
            ],
            [
                'name'        => 'get_file',
                'description' => 'Get the contents of a file in a GitLab repository.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Project ID or URL-encoded path.'],
                        'filePath'  => ['type' => 'string', 'description' => 'URL-encoded path to the file (e.g. "src%2Fmain.php").'],
                        'ref'       => ['type' => 'string', 'description' => 'Branch, tag, or commit SHA.', 'default' => 'main'],
                    ],
                    'required' => ['projectId', 'filePath'],
                ],
            ],
            [
                'name'        => 'list_branches',
                'description' => 'List branches in a GitLab repository.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Project ID or URL-encoded path.'],
                    ],
                    'required' => ['projectId'],
                ],
            ],
            [
                'name'        => 'list_pipelines',
                'description' => 'List CI/CD pipelines for a GitLab project.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'string', 'description' => 'Project ID or URL-encoded path.'],
                    ],
                    'required' => ['projectId'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_projects'        => $this->listProjects($params, $user),
            'get_project'          => $this->getProject($params, $user),
            'list_issues'          => $this->listIssues($params, $user),
            'create_issue'         => $this->createIssue($params, $user),
            'list_merge_requests'  => $this->listMergeRequests($params, $user),
            'create_merge_request' => $this->createMergeRequest($params, $user),
            'get_file'             => $this->getFile($params, $user),
            'list_branches'        => $this->listBranches($params, $user),
            'list_pipelines'       => $this->listPipelines($params, $user),
            default                => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    public function getAuthUrl(User $user): ?string
    {
        $state = bin2hex(random_bytes(16));

        $query = http_build_query([
            'client_id'     => config('services.gitlab.client_id'),
            'redirect_uri'  => config('services.gitlab.redirect'),
            'response_type' => 'code',
            'scope'         => 'read_api write_repository',
            'state'         => $state,
        ]);

        return self::OAUTH_AUTHORIZE_URL.'?'.$query;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function handleCallback(User $user, array $params): void
    {
        $code = (string) ($params['code'] ?? '');

        $response = Http::timeout(15)
            ->post(self::OAUTH_TOKEN_URL, [
                'client_id'     => config('services.gitlab.client_id'),
                'client_secret' => config('services.gitlab.client_secret'),
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => config('services.gitlab.redirect'),
            ]);

        if ($response->failed()) {
            return;
        }

        $data = $response->json();
        $token = $data['access_token'] ?? null;

        if ($token === null) {
            return;
        }

        $definition = $this->getDefinition();

        /** @var UserIntegration $integration */
        $integration = UserIntegration::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'integration_id' => $definition->getKey()],
            ['is_enabled' => true],
        );

        $integration->update([
            'is_enabled'          => true,
            'oauth_token'         => $token,
            'oauth_refresh_token' => $data['refresh_token'] ?? null,
            'oauth_expires_at'    => isset($data['expires_in'])
                ? now()->addSeconds((int) $data['expires_in'])
                : null,
        ]);
    }

    private function makeClient(User $user): PendingRequest
    {
        $integration = $this->getUserIntegration($user);
        $token = $integration?->oauth_token ?? ($this->getCredentials($user)['token'] ?? '');

        return Http::baseUrl(self::BASE_URL)
            ->timeout(20)
            ->connectTimeout(10)
            ->withHeaders([
                'PRIVATE-TOKEN' => (string) $token,
            ]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listProjects(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/projects', [
                'membership' => true,
                'per_page'   => $params['per_page'] ?? 20,
            ]);

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return ['projects' => $response->json()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getProject(array $params, User $user): array
    {
        try {
            $projectId = urlencode((string) $params['projectId']);
            $response = $this->makeClient($user)->get("/projects/{$projectId}");

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listIssues(array $params, User $user): array
    {
        try {
            $projectId = urlencode((string) $params['projectId']);
            $response = $this->makeClient($user)->get("/projects/{$projectId}/issues", [
                'state' => $params['state'] ?? 'opened',
            ]);

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return ['issues' => $response->json()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function createIssue(array $params, User $user): array
    {
        try {
            $projectId = urlencode((string) $params['projectId']);
            $body = ['title' => $params['title']];

            if (isset($params['description'])) {
                $body['description'] = $params['description'];
            }

            $response = $this->makeClient($user)->post("/projects/{$projectId}/issues", $body);

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listMergeRequests(array $params, User $user): array
    {
        try {
            $projectId = urlencode((string) $params['projectId']);
            $response = $this->makeClient($user)->get("/projects/{$projectId}/merge_requests", [
                'state' => $params['state'] ?? 'opened',
            ]);

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return ['merge_requests' => $response->json()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function createMergeRequest(array $params, User $user): array
    {
        try {
            $projectId = urlencode((string) $params['projectId']);
            $body = [
                'title'         => $params['title'],
                'source_branch' => $params['source_branch'],
                'target_branch' => $params['target_branch'],
            ];

            if (isset($params['description'])) {
                $body['description'] = $params['description'];
            }

            $response = $this->makeClient($user)->post("/projects/{$projectId}/merge_requests", $body);

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getFile(array $params, User $user): array
    {
        try {
            $projectId = urlencode((string) $params['projectId']);
            $filePath = urlencode((string) $params['filePath']);
            $ref = $params['ref'] ?? 'main';

            $response = $this->makeClient($user)->get(
                "/projects/{$projectId}/repository/files/{$filePath}",
                ['ref' => $ref],
            );

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            $data = $response->json();

            if (isset($data['content']) && $data['encoding'] === 'base64') {
                $data['decoded_content'] = base64_decode($data['content'], true);
            }

            return $data;
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listBranches(array $params, User $user): array
    {
        try {
            $projectId = urlencode((string) $params['projectId']);
            $response = $this->makeClient($user)->get("/projects/{$projectId}/repository/branches");

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return ['branches' => $response->json()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listPipelines(array $params, User $user): array
    {
        try {
            $projectId = urlencode((string) $params['projectId']);
            $response = $this->makeClient($user)->get("/projects/{$projectId}/pipelines");

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return ['pipelines' => $response->json()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
