<?php

declare(strict_types=1);

namespace App\Services\Integrations\Developer;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CloudflareService extends AbstractIntegrationService
{
    protected string $integrationName = 'cloudflare';

    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'list_zones',
                'description' => 'List all Cloudflare zones (domains) in the account.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'get_zone',
                'description' => 'Get details for a specific Cloudflare zone.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'zoneId' => ['type' => 'string', 'description' => 'The Cloudflare zone ID.'],
                    ],
                    'required' => ['zoneId'],
                ],
            ],
            [
                'name'        => 'list_dns_records',
                'description' => 'List DNS records for a Cloudflare zone.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'zoneId' => ['type' => 'string', 'description' => 'The Cloudflare zone ID.'],
                    ],
                    'required' => ['zoneId'],
                ],
            ],
            [
                'name'        => 'create_dns_record',
                'description' => 'Create a new DNS record in a Cloudflare zone.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'zoneId'  => ['type' => 'string', 'description' => 'The Cloudflare zone ID.'],
                        'type'    => ['type' => 'string', 'description' => 'DNS record type: A, AAAA, CNAME, TXT, MX, etc.'],
                        'name'    => ['type' => 'string', 'description' => 'DNS record name (e.g. "example.com" or "sub.example.com").'],
                        'content' => ['type' => 'string', 'description' => 'DNS record content (e.g. IP address for A records).'],
                        'proxied' => ['type' => 'boolean', 'description' => 'Whether the record should be proxied through Cloudflare.', 'default' => false],
                    ],
                    'required' => ['zoneId', 'type', 'name', 'content'],
                ],
            ],
            [
                'name'        => 'purge_cache',
                'description' => 'Purge all cached files for a Cloudflare zone.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'zoneId' => ['type' => 'string', 'description' => 'The Cloudflare zone ID.'],
                    ],
                    'required' => ['zoneId'],
                ],
            ],
            [
                'name'        => 'list_workers',
                'description' => 'List all Cloudflare Workers scripts in the account.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'get_worker',
                'description' => 'Get details for a specific Cloudflare Workers script.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'scriptName' => ['type' => 'string', 'description' => 'The name of the Workers script.'],
                    ],
                    'required' => ['scriptName'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_zones'        => $this->listZones($params, $user),
            'get_zone'          => $this->getZone($params, $user),
            'list_dns_records'  => $this->listDnsRecords($params, $user),
            'create_dns_record' => $this->createDnsRecord($params, $user),
            'purge_cache'       => $this->purgeCache($params, $user),
            'list_workers'      => $this->listWorkers($params, $user),
            'get_worker'        => $this->getWorker($params, $user),
            default             => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    private function makeClient(User $user): PendingRequest
    {
        $credentials = $this->getCredentials($user);
        $token = (string) ($credentials['api_token'] ?? config('services.cloudflare.api_token', ''));

        return Http::baseUrl(self::BASE_URL)
            ->timeout(20)
            ->connectTimeout(10)
            ->withToken($token);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function listZones(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get('/zones');

            if ($response->failed()) {
                return ['error' => $response->json('errors.0.message', 'Request failed'), 'status' => $response->status()];
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
    private function getZone(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/zones/{$params['zoneId']}");

            if ($response->failed()) {
                return ['error' => $response->json('errors.0.message', 'Request failed'), 'status' => $response->status()];
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
    private function listDnsRecords(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->get("/zones/{$params['zoneId']}/dns_records");

            if ($response->failed()) {
                return ['error' => $response->json('errors.0.message', 'Request failed'), 'status' => $response->status()];
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
    private function createDnsRecord(array $params, User $user): array
    {
        try {
            $body = [
                'type'    => $params['type'],
                'name'    => $params['name'],
                'content' => $params['content'],
                'proxied' => $params['proxied'] ?? false,
            ];

            $response = $this->makeClient($user)->post("/zones/{$params['zoneId']}/dns_records", $body);

            if ($response->failed()) {
                return ['error' => $response->json('errors.0.message', 'Request failed'), 'status' => $response->status()];
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
    private function purgeCache(array $params, User $user): array
    {
        try {
            $response = $this->makeClient($user)->post(
                "/zones/{$params['zoneId']}/purge_cache",
                ['purge_everything' => true],
            );

            if ($response->failed()) {
                return ['error' => $response->json('errors.0.message', 'Request failed'), 'status' => $response->status()];
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
    private function listWorkers(array $params, User $user): array
    {
        try {
            $credentials = $this->getCredentials($user);
            $accountId = (string) ($credentials['account_id'] ?? config('services.cloudflare.account_id', ''));

            $response = $this->makeClient($user)->get("/accounts/{$accountId}/workers/scripts");

            if ($response->failed()) {
                return ['error' => $response->json('errors.0.message', 'Request failed'), 'status' => $response->status()];
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
    private function getWorker(array $params, User $user): array
    {
        try {
            $credentials = $this->getCredentials($user);
            $accountId = (string) ($credentials['account_id'] ?? config('services.cloudflare.account_id', ''));

            $response = $this->makeClient($user)->get(
                "/accounts/{$accountId}/workers/scripts/{$params['scriptName']}",
            );

            if ($response->failed()) {
                return ['error' => $response->json('errors.0.message', 'Request failed'), 'status' => $response->status()];
            }

            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
