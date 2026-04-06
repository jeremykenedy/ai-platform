<?php

declare(strict_types=1);

namespace App\Services\Integrations\Developer;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubService extends AbstractIntegrationService
{
    protected string $integrationName = 'github';

    private const BASE_URL = 'https://api.github.com';

    private const OAUTH_AUTHORIZE_URL = 'https://github.com/login/oauth/authorize';

    private const OAUTH_TOKEN_URL = 'https://github.com/login/oauth/access_token';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'list_repos',
                'description' => 'List repositories for the authenticated user.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'per_page' => ['type' => 'integer', 'description' => 'Results per page (max 100).', 'default' => 30],
                        'sort'     => ['type' => 'string', 'description' => 'Sort by: created, updated, pushed, full_name.', 'default' => 'updated'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name'        => 'get_repo',
                'description' => 'Get a specific repository by owner and name.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner (user or org).'],
                        'repo'  => ['type' => 'string', 'description' => 'Repository name.'],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ],
            [
                'name'        => 'list_issues',
                'description' => 'List issues for a repository.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner.'],
                        'repo'  => ['type' => 'string', 'description' => 'Repository name.'],
                        'state' => ['type' => 'string', 'description' => 'Issue state: open, closed, all.', 'default' => 'open'],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ],
            [
                'name'        => 'create_issue',
                'description' => 'Create a new issue in a repository.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'owner'  => ['type' => 'string', 'description' => 'Repository owner.'],
                        'repo'   => ['type' => 'string', 'description' => 'Repository name.'],
                        'title'  => ['type' => 'string', 'description' => 'Issue title.'],
                        'body'   => ['type' => 'string', 'description' => 'Issue body.'],
                        'labels' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Labels to apply.'],
                    ],
                    'required' => ['owner', 'repo', 'title'],
                ],
            ],
            [
                'name'        => 'search_code',
                'description' => 'Search for code across GitHub repositories.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search query (supports GitHub code search syntax).'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'list_pull_requests',
                'description' => 'List pull requests for a repository.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner.'],
                        'repo'  => ['type' => 'string', 'description' => 'Repository name.'],
                        'state' => ['type' => 'string', 'description' => 'PR state: open, closed, all.', 'default' => 'open'],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ],
            [
                'name'        => 'create_pull_request',
                'description' => 'Create a new pull request.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner.'],
                        'repo'  => ['type' => 'string', 'description' => 'Repository name.'],
                        'title' => ['type' => 'string', 'description' => 'Pull request title.'],
                        'body'  => ['type' => 'string', 'description' => 'Pull request description.'],
                        'head'  => ['type' => 'string', 'description' => 'The branch containing changes (e.g. feature/my-feature).'],
                        'base'  => ['type' => 'string', 'description' => 'The branch to merge into (e.g. main).'],
                    ],
                    'required' => ['owner', 'repo', 'title', 'head', 'base'],
                ],
            ],
            [
                'name'        => 'get_file_contents',
                'description' => 'Get the contents of a file in a repository.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner.'],
                        'repo'  => ['type' => 'string', 'description' => 'Repository name.'],
                        'path'  => ['type' => 'string', 'description' => 'Path to the file.'],
                        'ref'   => ['type' => 'string', 'description' => 'Branch, tag, or commit SHA (defaults to repo default branch).'],
                    ],
                    'required' => ['owner', 'repo', 'path'],
                ],
            ],
            [
                'name'        => 'list_branches',
                'description' => 'List branches in a repository.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'owner' => ['type' => 'string', 'description' => 'Repository owner.'],
                        'repo'  => ['type' => 'string', 'description' => 'Repository name.'],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ],
            [
                'name'        => 'list_commits',
                'description' => 'List commits for a repository.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'owner'    => ['type' => 'string', 'description' => 'Repository owner.'],
                        'repo'     => ['type' => 'string', 'description' => 'Repository name.'],
                        'sha'      => ['type' => 'string', 'description' => 'Branch, tag, or commit SHA to start listing from.'],
                        'per_page' => ['type' => 'integer', 'description' => 'Results per page (max 100).', 'default' => 30],
                    ],
                    'required' => ['owner', 'repo'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_repos'          => $this->listRepos($params, $user),
            'get_repo'            => $this->getRepo($params, $user),
            'list_issues'         => $this->listIssues($params, $user),
            'create_issue'        => $this->createIssue($params, $user),
            'search_code'         => $this->searchCode($params, $user),
            'list_pull_requests'  => $this->listPullRequests($params, $user),
            'create_pull_request' => $this->createPullRequest($params, $user),
            'get_file_contents'   => $this->getFileContents($params, $user),
            'list_branches'       => $this->listBranches($params, $user),
            'list_commits'        => $this->listCommits($params, $user),
            default               => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    public function getAuthUrl(User $user): ?string
    {
        $state = bin2hex(random_bytes(16));

        $query = http_build_query([
            'client_id'    => config('services.github.client_id'),
            'redirect_uri' => config('services.github.redirect'),
            'scope'        => 'repo read:user user:email',
            'state'        => $state,
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
            ->withHeaders(['Accept' => 'application/json'])
            ->post(self::OAUTH_TOKEN_URL, [
                'client_id'     => config('services.github.client_id'),
                'client_secret' => config('services.github.client_secret'),
                'code'          => $code,
                'redirect_uri'  => config('services.github.redirect'),
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
            'is_enabled'     => true,
            'oauth_token'    => $token,
            'scopes_granted' => explode(',', (string) ($data['scope'] ?? '')),
        ]);
    }

    private function makeClient(User $user): PendingRequest
    {
        $integration = $this->getUserIntegration($user);
        $token = $integration?->oauth_token ?? ($this->getCredentials($user)['token'] ?? '');

        return Http::baseUrl(self::BASE_URL)
            ->timeout(20)
            ->connectTimeout(10)
            ->withToken((string) $token)
            ->withHeaders([
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listRepos(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/user/repos', [
                'per_page' => $params['per_page'] ?? 30,
                'sort'     => $params['sort'] ?? 'updated',
            ]);

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return ['repos' => $response->json()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getRepo(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/repos/{$params['owner']}/{$params['repo']}");

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
            $response = $this->makeClient($user)->get("/repos/{$params['owner']}/{$params['repo']}/issues", [
                'state' => $params['state'] ?? 'open',
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
            $body = [
                'title' => $params['title'],
            ];

            if (isset($params['body'])) {
                $body['body'] = $params['body'];
            }

            if (isset($params['labels'])) {
                $body['labels'] = $params['labels'];
            }

            $response = $this->makeClient($user)->post(
                "/repos/{$params['owner']}/{$params['repo']}/issues",
                $body,
            );

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
    private function searchCode(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/search/code', [
                'q' => $params['query'],
            ]);

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
    private function listPullRequests(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/repos/{$params['owner']}/{$params['repo']}/pulls", [
                'state' => $params['state'] ?? 'open',
            ]);

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return ['pull_requests' => $response->json()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function createPullRequest(array $params, User $user): array
    {
        try {
            $body = [
                'title' => $params['title'],
                'head'  => $params['head'],
                'base'  => $params['base'],
            ];

            if (isset($params['body'])) {
                $body['body'] = $params['body'];
            }

            $response = $this->makeClient($user)->post(
                "/repos/{$params['owner']}/{$params['repo']}/pulls",
                $body,
            );

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
    private function getFileContents(array $params, User $user): array
    {
        try {
            $query = [];

            if (isset($params['ref'])) {
                $query['ref'] = $params['ref'];
            }

            $response = $this->makeClient($user)->get(
                "/repos/{$params['owner']}/{$params['repo']}/contents/{$params['path']}",
                $query,
            );

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            $data = $response->json();

            if (isset($data['content']) && $data['encoding'] === 'base64') {
                $data['decoded_content'] = base64_decode(str_replace("\n", '', $data['content']), true);
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
            $response = $this->makeClient($user)->get(
                "/repos/{$params['owner']}/{$params['repo']}/branches",
            );

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
    private function listCommits(array $params, User $user): array
    {
        try {
            $query = [
                'per_page' => $params['per_page'] ?? 30,
            ];

            if (isset($params['sha'])) {
                $query['sha'] = $params['sha'];
            }

            $response = $this->makeClient($user)->get(
                "/repos/{$params['owner']}/{$params['repo']}/commits",
                $query,
            );

            if ($response->failed()) {
                return ['error' => $response->json('message', 'Request failed'), 'status' => $response->status()];
            }

            return ['commits' => $response->json()];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
