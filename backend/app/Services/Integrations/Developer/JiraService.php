<?php

declare(strict_types=1);

namespace App\Services\Integrations\Developer;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class JiraService extends AbstractIntegrationService
{
    protected string $integrationName = 'jira';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'search_issues',
                'description' => 'Search Jira issues using JQL (Jira Query Language).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'jql'        => ['type' => 'string', 'description' => 'JQL query string (e.g. "project = MY-PROJECT AND status = Open").'],
                        'maxResults' => ['type' => 'integer', 'description' => 'Maximum number of results to return.', 'default' => 50],
                    ],
                    'required' => ['jql'],
                ],
            ],
            [
                'name'        => 'get_issue',
                'description' => 'Get details for a specific Jira issue.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'issueKey' => ['type' => 'string', 'description' => 'Jira issue key (e.g. "MY-PROJECT-123").'],
                    ],
                    'required' => ['issueKey'],
                ],
            ],
            [
                'name'        => 'create_issue',
                'description' => 'Create a new Jira issue.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'projectKey'  => ['type' => 'string', 'description' => 'The project key (e.g. "MY-PROJECT").'],
                        'summary'     => ['type' => 'string', 'description' => 'Issue summary/title.'],
                        'description' => ['type' => 'string', 'description' => 'Issue description.'],
                        'issueType'   => ['type' => 'string', 'description' => 'Issue type name (e.g. "Story", "Bug", "Task").', 'default' => 'Task'],
                    ],
                    'required' => ['projectKey', 'summary'],
                ],
            ],
            [
                'name'        => 'update_issue',
                'description' => 'Update fields on an existing Jira issue.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'issueKey' => ['type' => 'string', 'description' => 'Jira issue key.'],
                        'fields'   => [
                            'type'        => 'object',
                            'description' => 'Fields to update as key-value pairs (e.g. {"summary": "New title", "priority": {"name": "High"}}).',
                        ],
                    ],
                    'required' => ['issueKey', 'fields'],
                ],
            ],
            [
                'name'        => 'add_comment',
                'description' => 'Add a comment to a Jira issue.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'issueKey' => ['type' => 'string', 'description' => 'Jira issue key.'],
                        'body'     => ['type' => 'string', 'description' => 'Comment text.'],
                    ],
                    'required' => ['issueKey', 'body'],
                ],
            ],
            [
                'name'        => 'list_projects',
                'description' => 'List all Jira projects accessible to the user.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'get_board',
                'description' => 'Get details for a specific Jira Agile board.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'boardId' => ['type' => 'integer', 'description' => 'The board ID.'],
                    ],
                    'required' => ['boardId'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'search_issues' => $this->searchIssues($params, $user),
            'get_issue'     => $this->getIssue($params, $user),
            'create_issue'  => $this->createIssue($params, $user),
            'update_issue'  => $this->updateIssue($params, $user),
            'add_comment'   => $this->addComment($params, $user),
            'list_projects' => $this->listProjects($params, $user),
            'get_board'     => $this->getBoard($params, $user),
            default         => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    private function makeClient(User $user): PendingRequest
    {
        $credentials = $this->getCredentials($user);
        $email = (string) ($credentials['email'] ?? '');
        $token = (string) ($credentials['api_token'] ?? '');
        $baseUrl = rtrim((string) ($credentials['base_url'] ?? ''), '/');

        $encoded = base64_encode("{$email}:{$token}");

        return Http::baseUrl($baseUrl)
            ->timeout(20)
            ->connectTimeout(10)
            ->withHeaders([
                'Authorization' => "Basic {$encoded}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function searchIssues(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->post('/issue/search', [
                'jql'        => $params['jql'],
                'maxResults' => $params['maxResults'] ?? 50,
            ]);

            if ($response->failed()) {
                return ['error' => $response->json('errorMessages.0', 'Request failed'), 'status' => $response->status()];
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
    private function getIssue(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/issue/{$params['issueKey']}");

            if ($response->failed()) {
                return ['error' => $response->json('errorMessages.0', 'Request failed'), 'status' => $response->status()];
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
    private function createIssue(array $params, User $user): array
    {
        try {
            $body = [
                'fields' => [
                    'project'   => ['key' => $params['projectKey']],
                    'summary'   => $params['summary'],
                    'issuetype' => ['name' => $params['issueType'] ?? 'Task'],
                ],
            ];

            if (isset($params['description'])) {
                $body['fields']['description'] = [
                    'type'    => 'doc',
                    'version' => 1,
                    'content' => [
                        [
                            'type'    => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => $params['description']],
                            ],
                        ],
                    ],
                ];
            }

            $response = $this->makeClient($user)->post('/issue', $body);

            if ($response->failed()) {
                return ['error' => $response->json('errorMessages.0', 'Request failed'), 'status' => $response->status()];
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
    private function updateIssue(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->put(
                "/issue/{$params['issueKey']}",
                ['fields' => $params['fields']],
            );

            if ($response->failed()) {
                return ['error' => $response->json('errorMessages.0', 'Request failed'), 'status' => $response->status()];
            }

            return ['success' => true, 'issueKey' => $params['issueKey']];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function addComment(array $params, User $user): array
    {
        try {
            $body = [
                'body' => [
                    'type'    => 'doc',
                    'version' => 1,
                    'content' => [
                        [
                            'type'    => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => $params['body']],
                            ],
                        ],
                    ],
                ],
            ];

            $response = $this->makeClient($user)->post("/issue/{$params['issueKey']}/comment", $body);

            if ($response->failed()) {
                return ['error' => $response->json('errorMessages.0', 'Request failed'), 'status' => $response->status()];
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
    private function listProjects(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/project/search');

            if ($response->failed()) {
                return ['error' => $response->json('errorMessages.0', 'Request failed'), 'status' => $response->status()];
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
    private function getBoard(array $params, User $user): array
    {
        try {
            $credentials = $this->getCredentials($user);
            $baseUrl = rtrim((string) ($credentials['base_url'] ?? ''), '/');
            $agileBaseUrl = str_replace('/rest/api/3', '', $baseUrl).'/rest/agile/1.0';

            $email = (string) ($credentials['email'] ?? '');
            $token = (string) ($credentials['api_token'] ?? '');
            $encoded = base64_encode("{$email}:{$token}");

            $response = Http::baseUrl($agileBaseUrl)
                ->timeout(20)
                ->connectTimeout(10)
                ->withHeaders([
                    'Authorization' => "Basic {$encoded}",
                    'Accept'        => 'application/json',
                ])
                ->get("/board/{$params['boardId']}");

            if ($response->failed()) {
                return ['error' => $response->json('errorMessages.0', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
