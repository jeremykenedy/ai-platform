<?php

declare(strict_types=1);

namespace App\Services\Integrations\Developer;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Http;

class LinearService extends AbstractIntegrationService
{
    protected string $integrationName = 'linear';

    private const BASE_URL = 'https://api.linear.app/graphql';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'list_issues',
                'description' => 'List Linear issues with optional filters.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'first' => ['type' => 'integer', 'description' => 'Number of issues to return.', 'default' => 25],
                        'filter' => ['type' => 'object', 'description' => 'Filter object (e.g. {"team": {"key": {"eq": "ENG"}}}).'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_issue',
                'description' => 'Get details for a specific Linear issue.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'issueId' => ['type' => 'string', 'description' => 'Linear issue ID.'],
                    ],
                    'required' => ['issueId'],
                ],
            ],
            [
                'name' => 'create_issue',
                'description' => 'Create a new Linear issue.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'teamId' => ['type' => 'string', 'description' => 'ID of the team to create the issue in.'],
                        'title' => ['type' => 'string', 'description' => 'Issue title.'],
                        'description' => ['type' => 'string', 'description' => 'Issue description (Markdown).'],
                        'priority' => ['type' => 'integer', 'description' => 'Priority (0=No priority, 1=Urgent, 2=High, 3=Medium, 4=Low).'],
                    ],
                    'required' => ['teamId', 'title'],
                ],
            ],
            [
                'name' => 'update_issue',
                'description' => 'Update a Linear issue.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'issueId' => ['type' => 'string', 'description' => 'Linear issue ID.'],
                        'state' => ['type' => 'string', 'description' => 'New state ID for the issue.'],
                        'assigneeId' => ['type' => 'string', 'description' => 'ID of the user to assign the issue to.'],
                    ],
                    'required' => ['issueId'],
                ],
            ],
            [
                'name' => 'list_teams',
                'description' => 'List all teams in the Linear workspace.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'list_projects',
                'description' => 'List all projects in the Linear workspace.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'search_issues',
                'description' => 'Search Linear issues by keyword.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search query string.'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_issues' => $this->listIssues($params, $user),
            'get_issue' => $this->getIssue($params, $user),
            'create_issue' => $this->createIssue($params, $user),
            'update_issue' => $this->updateIssue($params, $user),
            'list_teams' => $this->listTeams($params, $user),
            'list_projects' => $this->listProjects($params, $user),
            'search_issues' => $this->searchIssues($params, $user),
            default => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    /**
     * Execute a GraphQL query against the Linear API.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function graphql(User $user, string $query, array $variables = []): array
    {
        $credentials = $this->getCredentials($user);
        $token = (string) ($credentials['api_key'] ?? '');

        $response = Http::baseUrl(self::BASE_URL)
            ->timeout(20)
            ->connectTimeout(10)
            ->withToken($token)
            ->post('', [
                'query' => $query,
                'variables' => $variables,
            ]);

        if ($response->failed()) {
            return ['error' => 'GraphQL request failed', 'status' => $response->status()];
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listIssues(array $params, User $user): array
    {
        try {
            $first = (int) ($params['first'] ?? 25);
            $query = '
                query ListIssues($first: Int, $filter: IssueFilter) {
                    issues(first: $first, filter: $filter) {
                        nodes {
                            id
                            identifier
                            title
                            description
                            priority
                            state { id name }
                            assignee { id name email }
                            team { id name key }
                            createdAt
                            updatedAt
                        }
                    }
                }
            ';

            $variables = ['first' => $first];

            if (isset($params['filter'])) {
                $variables['filter'] = $params['filter'];
            }

            return $this->graphql($user, $query, $variables);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getIssue(array $params, User $user): array
    {
        try {
            $query = '
                query GetIssue($id: String!) {
                    issue(id: $id) {
                        id
                        identifier
                        title
                        description
                        priority
                        state { id name }
                        assignee { id name email }
                        team { id name key }
                        labels { nodes { id name } }
                        comments { nodes { id body createdAt user { name } } }
                        createdAt
                        updatedAt
                    }
                }
            ';

            return $this->graphql($user, $query, ['id' => $params['issueId']]);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createIssue(array $params, User $user): array
    {
        try {
            $query = '
                mutation CreateIssue($input: IssueCreateInput!) {
                    issueCreate(input: $input) {
                        success
                        issue {
                            id
                            identifier
                            title
                            url
                        }
                    }
                }
            ';

            $input = [
                'teamId' => $params['teamId'],
                'title' => $params['title'],
            ];

            if (isset($params['description'])) {
                $input['description'] = $params['description'];
            }

            if (isset($params['priority'])) {
                $input['priority'] = (int) $params['priority'];
            }

            return $this->graphql($user, $query, ['input' => $input]);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function updateIssue(array $params, User $user): array
    {
        try {
            $query = '
                mutation UpdateIssue($id: String!, $input: IssueUpdateInput!) {
                    issueUpdate(id: $id, input: $input) {
                        success
                        issue {
                            id
                            identifier
                            title
                            state { name }
                            assignee { name }
                        }
                    }
                }
            ';

            $input = [];

            if (isset($params['state'])) {
                $input['stateId'] = $params['state'];
            }

            if (isset($params['assigneeId'])) {
                $input['assigneeId'] = $params['assigneeId'];
            }

            return $this->graphql($user, $query, [
                'id' => $params['issueId'],
                'input' => $input,
            ]);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listTeams(array $params, User $user): array
    {
        try {
            $query = '
                query ListTeams {
                    teams {
                        nodes {
                            id
                            name
                            key
                            description
                        }
                    }
                }
            ';

            return $this->graphql($user, $query);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listProjects(array $params, User $user): array
    {
        try {
            $query = '
                query ListProjects {
                    projects {
                        nodes {
                            id
                            name
                            description
                            state
                            startDate
                            targetDate
                        }
                    }
                }
            ';

            return $this->graphql($user, $query);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function searchIssues(array $params, User $user): array
    {
        try {
            $query = '
                query SearchIssues($query: String!) {
                    issueSearch(query: $query) {
                        nodes {
                            id
                            identifier
                            title
                            state { name }
                            assignee { name }
                            team { name key }
                            createdAt
                        }
                    }
                }
            ';

            return $this->graphql($user, $query, ['query' => $params['query']]);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
