<?php

declare(strict_types=1);

namespace App\Services\Integrations\Productivity;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Http;

class GmailService extends AbstractIntegrationService
{
    protected string $integrationName = 'gmail';

    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const SCOPES = [
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.compose',
        'https://www.googleapis.com/auth/gmail.labels',
    ];

    public function getAuthUrl(User $user): ?string
    {
        $params = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $user->getKey(),
        ]);

        return self::AUTH_URL.'?'.$params;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function handleCallback(User $user, array $params): void
    {
        $code = (string) ($params['code'] ?? '');

        $response = Http::asForm()
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect_uri'),
                'grant_type' => 'authorization_code',
            ]);

        $response->throw();

        $data = $response->json();

        $definition = $this->getDefinition();

        UserIntegration::updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'integration_id' => $definition->getKey(),
            ],
            [
                'is_enabled' => true,
                'oauth_token' => $data['access_token'] ?? null,
                'oauth_refresh_token' => $data['refresh_token'] ?? null,
                'oauth_expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
                'scopes_granted' => self::SCOPES,
            ],
        );
    }

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'search_messages',
                'description' => 'Search Gmail messages using Gmail query syntax.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['query'],
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Gmail search query (e.g. "from:foo@bar.com is:unread").'],
                        'maxResults' => ['type' => 'integer', 'description' => 'Maximum number of results to return (default 10).'],
                    ],
                ],
            ],
            [
                'name' => 'read_message',
                'description' => 'Read the full content of a Gmail message.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['messageId'],
                    'properties' => [
                        'messageId' => ['type' => 'string', 'description' => 'Gmail message ID.'],
                    ],
                ],
            ],
            [
                'name' => 'read_thread',
                'description' => 'Read an entire Gmail conversation thread.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['threadId'],
                    'properties' => [
                        'threadId' => ['type' => 'string', 'description' => 'Gmail thread ID.'],
                    ],
                ],
            ],
            [
                'name' => 'create_draft',
                'description' => 'Create a Gmail draft message.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['to', 'subject', 'body'],
                    'properties' => [
                        'to' => ['type' => 'string', 'description' => 'Recipient email address.'],
                        'subject' => ['type' => 'string', 'description' => 'Email subject line.'],
                        'body' => ['type' => 'string', 'description' => 'Email body text.'],
                    ],
                ],
            ],
            [
                'name' => 'list_labels',
                'description' => 'List all Gmail labels for the user.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name' => 'get_profile',
                'description' => 'Get the user\'s Gmail profile (email address, message count, etc.).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'search_messages' => $this->searchMessages($params, $user),
            'read_message' => $this->readMessage($params, $user),
            'read_thread' => $this->readThread($params, $user),
            'create_draft' => $this->createDraft($params, $user),
            'list_labels' => $this->listLabels($user),
            'get_profile' => $this->getProfile($user),
            default => throw new \InvalidArgumentException("Unknown tool: {$toolName}"),
        };
    }

    public function testConnection(User $user): bool
    {
        try {
            $this->getProfile($user);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function searchMessages(array $params, User $user): array
    {
        $query = [
            'q' => (string) $params['query'],
            'maxResults' => (int) ($params['maxResults'] ?? 10),
        ];

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/messages', $query);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function readMessage(array $params, User $user): array
    {
        $messageId = (string) $params['messageId'];

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/messages/'.$messageId, ['format' => 'full']);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function readThread(array $params, User $user): array
    {
        $threadId = (string) $params['threadId'];

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/threads/'.$threadId, ['format' => 'full']);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createDraft(array $params, User $user): array
    {
        $to = (string) $params['to'];
        $subject = (string) $params['subject'];
        $body = (string) $params['body'];

        $rawMessage = "To: {$to}\r\nSubject: {$subject}\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n{$body}";
        $encoded = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/drafts', [
                'message' => ['raw' => $encoded],
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function listLabels(User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/labels');

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getProfile(User $user): array
    {
        $response = Http::withToken($this->getOauthToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/profile');

        $response->throw();

        return $response->json() ?? [];
    }

    private function getOauthToken(User $user): string
    {
        $integration = $this->getUserIntegration($user);

        return (string) ($integration?->oauth_token ?? '');
    }
}
