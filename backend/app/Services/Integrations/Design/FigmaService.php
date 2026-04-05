<?php

declare(strict_types=1);

namespace App\Services\Integrations\Design;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FigmaService extends AbstractIntegrationService
{
    protected string $integrationName = 'figma';

    private const BASE_URL = 'https://api.figma.com/v1';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'get_file',
                'description' => 'Retrieve a Figma file by its key, including all document nodes.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'fileKey' => [
                            'type' => 'string',
                            'description' => 'The Figma file key (found in the file URL).',
                        ],
                    ],
                    'required' => ['fileKey'],
                ],
            ],
            [
                'name' => 'get_file_nodes',
                'description' => 'Retrieve specific nodes from a Figma file by their IDs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'fileKey' => [
                            'type' => 'string',
                            'description' => 'The Figma file key.',
                        ],
                        'nodeIds' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Array of node IDs to retrieve.',
                        ],
                    ],
                    'required' => ['fileKey', 'nodeIds'],
                ],
            ],
            [
                'name' => 'list_projects',
                'description' => 'List all projects within a Figma team.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'teamId' => [
                            'type' => 'string',
                            'description' => 'The Figma team ID.',
                        ],
                    ],
                    'required' => ['teamId'],
                ],
            ],
            [
                'name' => 'get_comments',
                'description' => 'Retrieve all comments on a Figma file.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'fileKey' => [
                            'type' => 'string',
                            'description' => 'The Figma file key.',
                        ],
                    ],
                    'required' => ['fileKey'],
                ],
            ],
            [
                'name' => 'post_comment',
                'description' => 'Post a comment on a Figma file at a specific canvas position.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'fileKey' => [
                            'type' => 'string',
                            'description' => 'The Figma file key.',
                        ],
                        'message' => [
                            'type' => 'string',
                            'description' => 'The text content of the comment.',
                        ],
                        'x' => [
                            'type' => 'number',
                            'description' => 'Canvas X coordinate for the comment pin.',
                        ],
                        'y' => [
                            'type' => 'number',
                            'description' => 'Canvas Y coordinate for the comment pin.',
                        ],
                    ],
                    'required' => ['fileKey', 'message'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'get_file' => $this->getFile($user, $params),
            'get_file_nodes' => $this->getFileNodes($user, $params),
            'list_projects' => $this->listProjects($user, $params),
            'get_comments' => $this->getComments($user, $params),
            'post_comment' => $this->postComment($user, $params),
            default => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getFile(User $user, array $params): array
    {
        $fileKey = $params['fileKey'] ?? throw new RuntimeException('fileKey is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/files/'.urlencode((string) $fileKey));

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getFileNodes(User $user, array $params): array
    {
        $fileKey = $params['fileKey'] ?? throw new RuntimeException('fileKey is required.');
        $nodeIds = $params['nodeIds'] ?? throw new RuntimeException('nodeIds is required.');

        if (! is_array($nodeIds) || $nodeIds === []) {
            throw new RuntimeException('nodeIds must be a non-empty array.');
        }

        $response = $this->client($user)
            ->get(self::BASE_URL.'/files/'.urlencode((string) $fileKey).'/nodes', [
                'ids' => implode(',', $nodeIds),
            ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listProjects(User $user, array $params): array
    {
        $teamId = $params['teamId'] ?? throw new RuntimeException('teamId is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/teams/'.urlencode((string) $teamId).'/projects');

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getComments(User $user, array $params): array
    {
        $fileKey = $params['fileKey'] ?? throw new RuntimeException('fileKey is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/files/'.urlencode((string) $fileKey).'/comments');

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function postComment(User $user, array $params): array
    {
        $fileKey = $params['fileKey'] ?? throw new RuntimeException('fileKey is required.');
        $message = $params['message'] ?? throw new RuntimeException('message is required.');

        $body = ['message' => $message];

        if (isset($params['x']) || isset($params['y'])) {
            $body['client_meta'] = [
                'x' => (float) ($params['x'] ?? 0),
                'y' => (float) ($params['y'] ?? 0),
            ];
        }

        $response = $this->client($user)
            ->post(self::BASE_URL.'/files/'.urlencode((string) $fileKey).'/comments', $body);

        $response->throw();

        return $response->json();
    }

    private function getApiKey(User $user): string
    {
        $credentials = $this->getCredentials($user);

        if ($credentials === null || empty($credentials['api_key'])) {
            throw new RuntimeException('Figma API key is not configured for this user.');
        }

        return (string) $credentials['api_key'];
    }

    private function client(User $user): PendingRequest
    {
        return Http::withHeaders(['X-Figma-Token' => $this->getApiKey($user)])
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->acceptJson();
    }
}
